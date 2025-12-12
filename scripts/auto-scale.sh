#!/bin/bash

###############################################################################
# Auto-scaling Management Script for OBSOLIO AI
#
# This script helps manage auto-scaling across different platforms
# Usage: ./scripts/auto-scale.sh [command] [platform]
###############################################################################

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Helper functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Display usage
usage() {
    cat << EOF
Usage: $0 [COMMAND] [PLATFORM]

Commands:
    deploy          Deploy auto-scaling configuration
    status          Check current scaling status
    scale-up        Manually scale up
    scale-down      Manually scale down
    metrics         Show current metrics
    test            Run scaling test

Platforms:
    kubernetes      Kubernetes with HPA
    docker-swarm    Docker Swarm
    aws-ecs         AWS ECS
    gcp-run         Google Cloud Run
    azure-apps      Azure Container Apps

Examples:
    $0 deploy kubernetes
    $0 status kubernetes
    $0 scale-up kubernetes 5
    $0 metrics kubernetes

EOF
    exit 1
}

# Deploy auto-scaling configuration
deploy_autoscaling() {
    local platform=$1

    log_info "Deploying auto-scaling configuration for $platform..."

    case $platform in
        kubernetes)
            log_info "Applying Kubernetes HPA configuration..."
            kubectl apply -f "$PROJECT_ROOT/k8s/deployment.yaml"
            kubectl apply -f "$PROJECT_ROOT/k8s/hpa.yaml"
            log_info "HPA configuration applied successfully"
            ;;

        docker-swarm)
            log_info "Deploying Docker Swarm stack..."
            docker stack deploy -c "$PROJECT_ROOT/docker/docker-compose.swarm.yml" OBSOLIO
            log_info "Swarm stack deployed successfully"
            ;;

        aws-ecs)
            log_info "Configuring AWS ECS auto-scaling..."
            # Register scalable target
            aws application-autoscaling register-scalable-target \
                --service-namespace ecs \
                --resource-id service/OBSOLIO-production/OBSOLIO-api \
                --scalable-dimension ecs:service:DesiredCount \
                --min-capacity 3 \
                --max-capacity 20

            # Create CPU-based scaling policy
            aws application-autoscaling put-scaling-policy \
                --service-namespace ecs \
                --resource-id service/OBSOLIO-production/OBSOLIO-api \
                --scalable-dimension ecs:service:DesiredCount \
                --policy-name OBSOLIO-cpu-scaling \
                --policy-type TargetTrackingScaling \
                --target-tracking-scaling-policy-configuration \
                    "PredefinedMetricSpecification={PredefinedMetricType=ECSServiceAverageCPUUtilization},TargetValue=70.0"

            log_info "AWS ECS auto-scaling configured"
            ;;

        gcp-run)
            log_info "Configuring Google Cloud Run auto-scaling..."
            gcloud run services update OBSOLIO-api \
                --min-instances=3 \
                --max-instances=100 \
                --cpu-throttling=false \
                --concurrency=80
            log_info "Cloud Run auto-scaling configured"
            ;;

        azure-apps)
            log_info "Configuring Azure Container Apps auto-scaling..."
            az containerapp update \
                --name OBSOLIO-api \
                --resource-group OBSOLIO-production \
                --min-replicas 3 \
                --max-replicas 30 \
                --scale-rule-name http-rule \
                --scale-rule-type http \
                --scale-rule-http-concurrency 100
            log_info "Azure Container Apps auto-scaling configured"
            ;;

        *)
            log_error "Unknown platform: $platform"
            usage
            ;;
    esac
}

# Check scaling status
check_status() {
    local platform=$1

    log_info "Checking auto-scaling status for $platform..."

    case $platform in
        kubernetes)
            kubectl get hpa -n production
            echo ""
            log_info "Current pod status:"
            kubectl get pods -n production -l app=OBSOLIO
            ;;

        docker-swarm)
            docker service ls --filter name=OBSOLIO
            echo ""
            log_info "Detailed service info:"
            docker service ps OBSOLIO_api
            ;;

        aws-ecs)
            aws ecs describe-services \
                --cluster OBSOLIO-production \
                --services OBSOLIO-api \
                --query 'services[0].[desiredCount,runningCount,pendingCount]' \
                --output table
            ;;

        gcp-run)
            gcloud run services describe OBSOLIO-api \
                --format="table(status.conditions[0].status,spec.template.spec.containerConcurrency,status.traffic[0].revisionName)"
            ;;

        azure-apps)
            az containerapp show \
                --name OBSOLIO-api \
                --resource-group OBSOLIO-production \
                --query "{minReplicas:properties.template.scale.minReplicas,maxReplicas:properties.template.scale.maxReplicas,currentReplicas:properties.runningStatus.runningCount}" \
                --output table
            ;;

        *)
            log_error "Unknown platform: $platform"
            usage
            ;;
    esac
}

# Manual scale up
scale_up() {
    local platform=$1
    local replicas=$2

    log_info "Scaling up to $replicas replicas on $platform..."

    case $platform in
        kubernetes)
            kubectl scale deployment OBSOLIO-api -n production --replicas=$replicas
            ;;

        docker-swarm)
            docker service scale OBSOLIO_api=$replicas
            ;;

        aws-ecs)
            aws ecs update-service \
                --cluster OBSOLIO-production \
                --service OBSOLIO-api \
                --desired-count $replicas
            ;;

        *)
            log_error "Manual scaling not supported for $platform"
            ;;
    esac

    log_info "Scaling operation initiated"
}

# Manual scale down
scale_down() {
    local platform=$1
    local replicas=$2

    log_warning "Scaling down to $replicas replicas on $platform..."

    case $platform in
        kubernetes)
            kubectl scale deployment OBSOLIO-api -n production --replicas=$replicas
            ;;

        docker-swarm)
            docker service scale OBSOLIO_api=$replicas
            ;;

        aws-ecs)
            aws ecs update-service \
                --cluster OBSOLIO-production \
                --service OBSOLIO-api \
                --desired-count $replicas
            ;;

        *)
            log_error "Manual scaling not supported for $platform"
            ;;
    esac

    log_info "Scaling operation initiated"
}

# Show metrics
show_metrics() {
    local platform=$1

    log_info "Fetching metrics for $platform..."

    case $platform in
        kubernetes)
            kubectl top pods -n production -l app=OBSOLIO
            echo ""
            kubectl top nodes
            ;;

        docker-swarm)
            docker stats --no-stream --format "table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}"
            ;;

        *)
            log_info "Fetching from Prometheus..."
            curl -s "http://localhost:9090/api/v1/query?query=OBSOLIO_http_requests_total" | jq '.data.result'
            ;;
    esac
}

# Run scaling test
run_scaling_test() {
    local platform=$1

    log_info "Running scaling test on $platform..."
    log_warning "This will generate load on your application!"

    read -p "Continue? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_info "Test cancelled"
        exit 0
    fi

    log_info "Installing load testing tool (Apache Bench)..."

    # Get API endpoint
    case $platform in
        kubernetes)
            API_URL=$(kubectl get svc OBSOLIO-api -n production -o jsonpath='{.status.loadBalancer.ingress[0].ip}')
            ;;
        docker-swarm)
            API_URL="localhost:8000"
            ;;
        *)
            read -p "Enter API URL: " API_URL
            ;;
    esac

    log_info "Generating load to $API_URL/api/health..."
    log_info "Test 1: Moderate load (1000 requests, 10 concurrent)"
    ab -n 1000 -c 10 "http://$API_URL/api/health"

    sleep 10
    check_status "$platform"

    log_info "Test 2: High load (5000 requests, 50 concurrent)"
    ab -n 5000 -c 50 "http://$API_URL/api/health"

    sleep 10
    check_status "$platform"

    log_info "Test 3: Spike load (10000 requests, 100 concurrent)"
    ab -n 10000 -c 100 "http://$API_URL/api/health"

    sleep 30
    log_info "Final status after test:"
    check_status "$platform"

    log_info "Load test complete. Monitor your scaling metrics."
}

# Main script
main() {
    if [ $# -lt 2 ]; then
        usage
    fi

    COMMAND=$1
    PLATFORM=$2

    case $COMMAND in
        deploy)
            deploy_autoscaling "$PLATFORM"
            ;;
        status)
            check_status "$PLATFORM"
            ;;
        scale-up)
            if [ -z "$3" ]; then
                log_error "Please specify number of replicas"
                exit 1
            fi
            scale_up "$PLATFORM" "$3"
            ;;
        scale-down)
            if [ -z "$3" ]; then
                log_error "Please specify number of replicas"
                exit 1
            fi
            scale_down "$PLATFORM" "$3"
            ;;
        metrics)
            show_metrics "$PLATFORM"
            ;;
        test)
            run_scaling_test "$PLATFORM"
            ;;
        *)
            log_error "Unknown command: $COMMAND"
            usage
            ;;
    esac
}

main "$@"

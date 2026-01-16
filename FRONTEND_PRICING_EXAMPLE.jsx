// OBSOLIO Pricing Page - React Example Implementation
// This is a complete, production-ready example

import React, { useState, useEffect, useMemo } from 'react';
import './PricingPage.css'; // Your styles

const PricingPage = () => {
  // State management
  const [billingCycle, setBillingCycle] = useState('monthly');
  const [plans, setPlans] = useState({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Fetch plans from API
  useEffect(() => {
    const fetchPlans = async () => {
      try {
        setLoading(true);
        setError(null);

        const response = await fetch('https://api.obsolio.com/api/v1/pricing/plans');
        const data = await response.json();

        if (data.success) {
          setPlans(data.data);
        } else {
          throw new Error('Failed to load pricing plans');
        }
      } catch (err) {
        console.error('Error fetching plans:', err);
        setError(err.message || 'Failed to load pricing. Please try again.');
      } finally {
        setLoading(false);
      }
    };

    fetchPlans();
  }, []);

  // Get plan names sorted by display order
  const planNames = useMemo(() => {
    return Object.keys(plans).sort((a, b) => {
      const planA = plans[a][0];
      const planB = plans[b][0];
      return (planA?.display_order || 0) - (planB?.display_order || 0);
    });
  }, [plans]);

  // Get the selected plan variant (monthly or annual)
  const getSelectedPlan = (planName) => {
    const variants = plans[planName] || [];
    return variants.find(v => v.billing_cycle.code === billingCycle) || variants[0];
  };

  // Calculate monthly equivalent for annual plans
  const getMonthlyEquivalent = (plan) => {
    if (plan.billing_cycle.code === 'monthly') {
      return plan.final_price;
    }
    return (parseFloat(plan.final_price) / 12).toFixed(2);
  };

  // Calculate savings percentage
  const getSavingsPercentage = (planName) => {
    const variants = plans[planName] || [];
    const monthly = variants.find(v => v.billing_cycle.code === 'monthly');
    const annual = variants.find(v => v.billing_cycle.code === 'annual');

    if (!monthly || !annual) return null;

    const monthlyCost = parseFloat(monthly.final_price);
    const annualMonthlyEquivalent = parseFloat(annual.final_price) / 12;
    const savings = ((monthlyCost - annualMonthlyEquivalent) / monthlyCost * 100);

    return Math.round(savings);
  };

  // Format price for display
  const formatPrice = (price) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(price);
  };

  // Check if value represents unlimited
  const isUnlimited = (value) => value === null || value >= 999999;

  // Display value or "Unlimited"
  const displayValue = (value, unit = '') => {
    if (isUnlimited(value)) return 'Unlimited';
    return `${value.toLocaleString()}${unit}`;
  };

  // Handle plan selection
  const handleSelectPlan = (plan) => {
    // Build query parameters
    const params = new URLSearchParams({
      plan: plan.id,
      billing: plan.billing_cycle.code
    });

    // Redirect to signup with plan preselected
    // Adjust this URL based on your routing
    window.location.href = `/signup?${params.toString()}`;
  };

  // Loading state
  if (loading) {
    return (
      <div className="pricing-page">
        <div className="pricing-loading">
          <div className="spinner"></div>
          <p>Loading pricing plans...</p>
        </div>
      </div>
    );
  }

  // Error state
  if (error) {
    return (
      <div className="pricing-page">
        <div className="pricing-error">
          <h2>Oops! Something went wrong</h2>
          <p>{error}</p>
          <button onClick={() => window.location.reload()}>
            Try Again
          </button>
        </div>
      </div>
    );
  }

  // Empty state
  if (planNames.length === 0) {
    return (
      <div className="pricing-page">
        <div className="pricing-empty">
          <h2>No Plans Available</h2>
          <p>Pricing plans are not available at the moment. Please check back later.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="pricing-page">
      {/* Header */}
      <header className="pricing-header">
        <h1>Choose Your Plan</h1>
        <p>Start with a 14-day free trial. No credit card required.</p>
      </header>

      {/* Billing Toggle */}
      <div className="billing-toggle">
        <button
          className={`toggle-button ${billingCycle === 'monthly' ? 'active' : ''}`}
          onClick={() => setBillingCycle('monthly')}
        >
          Monthly
        </button>
        <button
          className={`toggle-button ${billingCycle === 'annual' ? 'active' : ''}`}
          onClick={() => setBillingCycle('annual')}
        >
          Annual
          {billingCycle === 'annual' && (
            <span className="savings-badge">Save up to 17%</span>
          )}
        </button>
      </div>

      {/* Pricing Grid */}
      <div className="pricing-grid">
        {planNames.map(planName => {
          const plan = getSelectedPlan(planName);
          const savings = getSavingsPercentage(planName);
          const isPopular = planName === 'Professional';
          const isEnterprise = planName === 'Enterprise';

          return (
            <div
              key={planName}
              className={`pricing-card ${isPopular ? 'popular' : ''} ${isEnterprise ? 'enterprise' : ''}`}
            >
              {/* Popular Badge */}
              {isPopular && (
                <div className="badge popular-badge">Most Popular</div>
              )}

              {/* Plan Header */}
              <div className="card-header">
                <h2 className="plan-name">{plan.name}</h2>
                <p className="plan-description">{plan.description}</p>
              </div>

              {/* Price Display */}
              <div className="card-pricing">
                {billingCycle === 'annual' && savings > 0 && (
                  <div className="savings-label">
                    Save {savings}%
                  </div>
                )}

                <div className="price-display">
                  <span className="price-amount">{formatPrice(plan.final_price)}</span>
                  <span className="price-period">
                    /{plan.billing_cycle.name.toLowerCase()}
                  </span>
                </div>

                {billingCycle === 'annual' && (
                  <div className="monthly-equivalent">
                    {formatPrice(getMonthlyEquivalent(plan))}/month when billed annually
                  </div>
                )}

                {plan.trial_days > 0 && (
                  <div className="trial-info">
                    {plan.trial_days}-day free trial
                  </div>
                )}
              </div>

              {/* Features List */}
              <div className="card-features">
                <ul className="features-list">
                  {/* Executions */}
                  <li className="feature-item">
                    <svg className="checkmark" viewBox="0 0 20 20" fill="currentColor">
                      <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                    </svg>
                    <span>
                      {isUnlimited(plan.included_executions)
                        ? 'Unlimited agent executions'
                        : `${plan.included_executions.toLocaleString()} agent executions/month`}
                    </span>
                  </li>

                  {/* Agents */}
                  <li className="feature-item">
                    <svg className="checkmark" viewBox="0 0 20 20" fill="currentColor">
                      <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                    </svg>
                    <span>
                      {isUnlimited(plan.max_agents)
                        ? 'Unlimited agents (all tiers)'
                        : `Up to ${plan.max_agents.toLocaleString()} agents`}
                    </span>
                  </li>

                  {/* Storage */}
                  <li className="feature-item">
                    <svg className="checkmark" viewBox="0 0 20 20" fill="currentColor">
                      <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                    </svg>
                    <span>
                      {isUnlimited(plan.storage_gb)
                        ? 'Unlimited storage'
                        : `${plan.storage_gb}GB storage`}
                    </span>
                  </li>

                  {/* Users */}
                  {!isUnlimited(plan.max_users) && (
                    <li className="feature-item">
                      <svg className="checkmark" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                      </svg>
                      <span>
                        Up to {plan.max_users} team members
                      </span>
                    </li>
                  )}

                  {/* Additional Features */}
                  {plan.features && plan.features.slice(0, 5).map((feature, idx) => (
                    <li key={idx} className="feature-item">
                      <svg className="checkmark" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                      </svg>
                      <span>{feature}</span>
                    </li>
                  ))}
                </ul>
              </div>

              {/* CTA Button */}
              <div className="card-cta">
                {isEnterprise ? (
                  <button
                    className="cta-button secondary"
                    onClick={() => window.location.href = '/contact-sales'}
                  >
                    Contact Sales
                  </button>
                ) : (
                  <button
                    className={`cta-button ${isPopular ? 'primary' : 'secondary'}`}
                    onClick={() => handleSelectPlan(plan)}
                  >
                    {plan.trial_days > 0 ? 'Start Free Trial' : 'Get Started'}
                  </button>
                )}
              </div>

              {/* Overage Pricing */}
              {plan.overage_price_per_execution && (
                <div className="overage-info">
                  Additional executions: {formatPrice(plan.overage_price_per_execution)}/execution
                </div>
              )}
            </div>
          );
        })}
      </div>

      {/* FAQ or Additional Info */}
      <div className="pricing-footer">
        <p>All plans include a free trial. No credit card required.</p>
        <p>
          <a href="/pricing/faq">View FAQ</a> |{' '}
          <a href="/contact">Contact Sales</a>
        </p>
      </div>
    </div>
  );
};

export default PricingPage;

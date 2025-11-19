<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseAgentRequest;
use App\Http\Resources\MarketplaceListingResource;
use App\Http\Resources\MarketplacePurchaseResource;
use App\Models\Agent;
use App\Models\MarketplaceListing;
use App\Models\MarketplacePurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MarketplaceController extends Controller
{
    /**
     * Display a listing of marketplace listings.
     * Browse available agents for purchase.
     */
    public function index(): AnonymousResourceCollection
    {
        $query = MarketplaceListing::where('is_approved', true)
            ->where('status', 'active')
            ->with(['agent', 'sellerTenant']);

        // Filter by category if provided
        if (request('category')) {
            $query->where('category', request('category'));
        }

        // Filter by industry if provided
        if (request('industry')) {
            $query->where('industry', request('industry'));
        }

        // Filter by price type if provided
        if (request('price_type')) {
            $query->where('price_type', request('price_type'));
        }

        // Search by title or description
        if (request('search')) {
            $search = request('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sort by
        $sortBy = request('sort_by', 'created_at');
        $sortOrder = request('sort_order', 'desc');

        if (in_array($sortBy, ['created_at', 'price', 'views_count', 'purchases_count', 'rating_average'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $listings = $query->paginate(request('per_page', 15));

        // Increment views count for each listing
        foreach ($listings as $listing) {
            $listing->increment('views_count');
        }

        return MarketplaceListingResource::collection($listings);
    }

    /**
     * Display the specified marketplace listing.
     */
    public function show(MarketplaceListing $marketplaceListing): MarketplaceListingResource
    {
        $marketplaceListing->load(['agent', 'sellerTenant'])
            ->loadCount(['purchases']);

        // Increment views count
        $marketplaceListing->increment('views_count');

        return new MarketplaceListingResource($marketplaceListing);
    }

    /**
     * Purchase an agent from the marketplace.
     */
    public function purchase(PurchaseAgentRequest $request): JsonResponse
    {
        $listingId = $request->validated('listing_id');
        $listing = MarketplaceListing::findOrFail($listingId);

        // Check if listing is available
        if ($listing->status !== 'active' || !$listing->is_approved) {
            return response()->json([
                'message' => 'This listing is not available for purchase.',
            ], 422);
        }

        // Check if already purchased by this tenant
        $existingPurchase = MarketplacePurchase::where('listing_id', $listing->id)
            ->where('buyer_tenant_id', tenant('id'))
            ->first();

        if ($existingPurchase) {
            return response()->json([
                'message' => 'You have already purchased this agent.',
            ], 422);
        }

        // Create purchase record
        $purchase = MarketplacePurchase::create([
            'listing_id' => $listing->id,
            'buyer_tenant_id' => tenant('id'),
            'purchased_by_user_id' => auth()->id(),
            'price_paid' => $listing->price,
            'currency' => $listing->currency,
        ]);

        // Clone the agent for the buyer's tenant
        $originalAgent = $listing->agent;
        $clonedAgent = $originalAgent->replicate();
        $clonedAgent->tenant_id = tenant('id');
        $clonedAgent->created_by_user_id = auth()->id();
        $clonedAgent->is_published = false;
        $clonedAgent->marketplace_listing_id = null;
        $clonedAgent->save();

        // Update listing statistics
        $listing->increment('purchases_count');

        activity()
            ->performedOn($purchase)
            ->causedBy(auth()->user())
            ->withProperties([
                'listing_id' => $listing->id,
                'agent_id' => $clonedAgent->id,
            ])
            ->log('Marketplace agent purchased');

        $purchase->load(['listing', 'purchasedBy']);

        return (new MarketplacePurchaseResource($purchase))
            ->response()
            ->setStatusCode(201);
    }
}

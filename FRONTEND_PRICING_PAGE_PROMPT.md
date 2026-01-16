# Frontend Pricing Page - Complete Implementation Guide

## Overview
Build a modern, responsive pricing page for OBSOLIO that displays subscription plans with different billing cycles (monthly, annual, etc.) and allows users to compare plans and select their preferred option.

---

## API Endpoint

### Get All Subscription Plans
**Endpoint:** `GET https://api.obsolio.com/api/v1/pricing/plans`
**Authentication:** None required (public endpoint)
**Method:** GET

### Response Structure
```json
{
  "success": true,
  "data": {
    "Starter": [
      {
        "id": "uuid-here",
        "name": "Starter",
        "description": "Perfect for individuals and small teams getting started",
        "tier": "starter",
        "billing_cycle_id": "uuid-monthly",
        "base_price": "29.00",
        "final_price": "29.00",
        "included_executions": 1000,
        "overage_price_per_execution": "0.0100",
        "max_users": 3,
        "max_agents": 5,
        "max_agent_slots": 5,
        "max_basic_agents": 5,
        "max_professional_agents": 0,
        "max_specialized_agents": 0,
        "max_enterprise_agents": 0,
        "storage_gb": 10,
        "trial_days": 14,
        "features": [
          "1,000 agent executions/month",
          "Up to 5 Basic agents",
          "10GB storage",
          "Email support",
          "Basic analytics"
        ],
        "highlight_features": [
          "Perfect for individuals",
          "14-day free trial"
        ],
        "is_active": true,
        "is_published": true,
        "display_order": 1,
        "created_at": "2024-01-15T10:00:00.000000Z",
        "updated_at": "2024-01-15T10:00:00.000000Z",
        "billing_cycle": {
          "id": "uuid-monthly",
          "code": "monthly",
          "name": "Monthly",
          "months": 1,
          "discount_percentage": "0.00"
        }
      },
      {
        "id": "uuid-here-2",
        "name": "Starter",
        "description": "Perfect for individuals and small teams getting started",
        "tier": "starter",
        "billing_cycle_id": "uuid-annual",
        "base_price": "290.00",
        "final_price": "290.00",
        "included_executions": 1000,
        "overage_price_per_execution": "0.0100",
        "max_users": 3,
        "max_agents": 5,
        "storage_gb": 10,
        "trial_days": 14,
        "features": [
          "1,000 agent executions/month",
          "Up to 5 Basic agents",
          "10GB storage",
          "Email support",
          "Basic analytics"
        ],
        "highlight_features": [
          "Save 17% with annual billing",
          "14-day free trial"
        ],
        "billing_cycle": {
          "id": "uuid-annual",
          "code": "annual",
          "name": "Annual",
          "months": 12,
          "discount_percentage": "17.00"
        }
      }
    ],
    "Professional": [
      {
        "id": "uuid-here-3",
        "name": "Professional",
        "description": "For growing teams that need more power and flexibility",
        "tier": "professional",
        "billing_cycle_id": "uuid-monthly",
        "base_price": "99.00",
        "final_price": "99.00",
        "included_executions": 5000,
        "overage_price_per_execution": "0.0080",
        "max_users": 10,
        "max_agents": 20,
        "max_agent_slots": 20,
        "max_basic_agents": 20,
        "max_professional_agents": 5,
        "max_specialized_agents": 0,
        "max_enterprise_agents": 0,
        "storage_gb": 50,
        "trial_days": 14,
        "features": [
          "5,000 agent executions/month",
          "Up to 20 agents (including 5 Professional)",
          "50GB storage",
          "Priority email support",
          "Advanced analytics",
          "Custom integrations",
          "Team collaboration tools"
        ],
        "highlight_features": [
          "Most popular",
          "Best for growing teams"
        ],
        "is_active": true,
        "is_published": true,
        "display_order": 2,
        "billing_cycle": {
          "id": "uuid-monthly",
          "code": "monthly",
          "name": "Monthly",
          "months": 1,
          "discount_percentage": "0.00"
        }
      }
    ],
    "Enterprise": [
      {
        "id": "uuid-here-4",
        "name": "Enterprise",
        "description": "For large organizations with advanced needs",
        "tier": "enterprise",
        "billing_cycle_id": "uuid-monthly",
        "base_price": "499.00",
        "final_price": "499.00",
        "included_executions": 50000,
        "overage_price_per_execution": "0.0050",
        "max_users": null,
        "max_agents": null,
        "max_agent_slots": null,
        "max_basic_agents": null,
        "max_professional_agents": null,
        "max_specialized_agents": null,
        "max_enterprise_agents": null,
        "storage_gb": null,
        "trial_days": 30,
        "features": [
          "50,000+ agent executions/month",
          "Unlimited agents (all tiers)",
          "Unlimited storage",
          "24/7 dedicated support",
          "Advanced analytics & reporting",
          "Custom integrations",
          "SSO & advanced security",
          "Dedicated account manager",
          "SLA guarantee"
        ],
        "highlight_features": [
          "Unlimited everything",
          "30-day trial"
        ],
        "is_active": true,
        "is_published": true,
        "display_order": 3,
        "billing_cycle": {
          "id": "uuid-monthly",
          "code": "monthly",
          "name": "Monthly",
          "months": 1,
          "discount_percentage": "0.00"
        }
      }
    ]
  }
}
```

---

## UI/UX Requirements

### 1. **Billing Toggle**
- Display a toggle switch at the top: **Monthly** / **Annual**
- When toggled, update all displayed prices to show the selected billing cycle
- Show savings percentage badge for annual billing (e.g., "Save 17%")

### 2. **Plan Cards Layout**
- Display plans in a responsive grid (3 columns on desktop, 1-2 on mobile)
- Each plan card should include:
  - **Plan Name** (e.g., "Starter", "Professional", "Enterprise")
  - **Description** (short tagline)
  - **Price Display:**
    - Large, prominent price (e.g., "$29/month" or "$290/year")
    - If annual: show "Billed annually" and monthly equivalent (e.g., "$24.17/month")
    - Show discount badge if annual (e.g., "Save 17%")
  - **Highlight Badge** (optional): "Most Popular", "Best Value", etc.
  - **Key Features List:**
    - Agent executions included
    - Number of agents allowed (by tier)
    - Storage limit
    - Support level
    - Additional features
  - **CTA Button:**
    - Primary: "Start Free Trial" (if trial_days > 0)
    - Secondary: "Get Started" or "Contact Sales" (for Enterprise)
  - **Trial Information:** Show trial period if available (e.g., "14-day free trial")

### 3. **Highlight the "Most Popular" Plan**
- Add visual distinction (e.g., border color, shadow, badge)
- Usually the Professional/mid-tier plan

### 4. **Responsive Design**
- **Desktop (≥1024px):** 3-column grid
- **Tablet (768px-1023px):** 2-column grid
- **Mobile (<768px):** Single column, stacked cards

### 5. **Feature Comparison Table** (Optional Enhancement)
- Add a "Compare Plans" section below the cards
- Show detailed feature comparison in a table format
- Checkmarks for included features, X for excluded

### 6. **Agent Tier Breakdown**
Display agent limits clearly:
```
Starter: Up to 5 Basic agents
Professional: Up to 20 agents (including 5 Professional)
Enterprise: Unlimited agents (all tiers)
```

### 7. **Overage Pricing Display**
Show overage pricing transparently:
```
Additional executions: $0.01 per execution
or
Additional executions: $0.008 per execution (Professional)
```

---

## Key Business Logic

### 1. **Price Calculations**
```javascript
// Monthly equivalent for annual plans
const monthlyEquivalent = (annualPrice / 12).toFixed(2);

// Savings calculation
const monthlyCost = monthlyPlan.final_price;
const annualCost = annualPlan.final_price;
const annualMonthlyEquivalent = annualCost / 12;
const savings = ((monthlyCost - annualMonthlyEquivalent) / monthlyCost * 100).toFixed(0);
```

### 2. **Data Grouping**
Plans are grouped by name (Starter, Professional, Enterprise), each containing multiple billing cycles.

```javascript
// Example data structure transformation
const plans = response.data;
const planNames = Object.keys(plans); // ["Starter", "Professional", "Enterprise"]

// For each plan, find monthly and annual versions
planNames.forEach(planName => {
  const variants = plans[planName];
  const monthly = variants.find(v => v.billing_cycle.code === 'monthly');
  const annual = variants.find(v => v.billing_cycle.code === 'annual');

  // Display the selected billing cycle
  const selectedPlan = billingCycle === 'annual' ? annual : monthly;
});
```

### 3. **Feature Display Priority**
1. Show `highlight_features` prominently (above the fold)
2. Display `features` array as bulleted list
3. Show agent limits based on tier:
   - Basic: `max_basic_agents`
   - Professional: `max_professional_agents`
   - Specialized: `max_specialized_agents`
   - Enterprise: `max_enterprise_agents`

### 4. **Null Value Handling**
- If `max_users`, `max_agents`, or `storage_gb` is `null`, display as "Unlimited"
- Example: `plan.max_users ?? 'Unlimited'`

---

## Technical Implementation

### 1. **State Management**
```javascript
const [billingCycle, setBillingCycle] = useState('monthly'); // or 'annual'
const [plans, setPlans] = useState({});
const [loading, setLoading] = useState(true);
const [error, setError] = useState(null);
```

### 2. **API Call**
```javascript
useEffect(() => {
  const fetchPlans = async () => {
    try {
      setLoading(true);
      const response = await fetch('https://api.obsolio.com/api/v1/pricing/plans');
      const data = await response.json();

      if (data.success) {
        setPlans(data.data);
      } else {
        setError('Failed to load pricing plans');
      }
    } catch (err) {
      setError('Network error. Please try again later.');
    } finally {
      setLoading(false);
    }
  };

  fetchPlans();
}, []);
```

### 3. **Plan Selection Handler**
```javascript
const handleSelectPlan = (planId, billingCycleCode) => {
  // If user is authenticated, redirect to checkout/signup
  // If not authenticated, redirect to signup with plan preselected

  const params = new URLSearchParams({
    plan: planId,
    billing: billingCycleCode
  });

  // Redirect to signup or checkout
  window.location.href = `/signup?${params.toString()}`;
};
```

### 4. **Price Formatting**
```javascript
const formatPrice = (price) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(price);
};

// Usage
formatPrice(29.00); // "$29"
formatPrice(290.00); // "$290"
```

---

## Example Component Structure

```jsx
<PricingPage>
  <Header>
    <h1>Choose Your Plan</h1>
    <p>Start with a 14-day free trial. No credit card required.</p>
  </Header>

  <BillingToggle
    value={billingCycle}
    onChange={setBillingCycle}
    options={['monthly', 'annual']}
  />

  <PricingGrid>
    {planNames.map(planName => (
      <PricingCard
        key={planName}
        plan={getSelectedPlan(planName, billingCycle)}
        isPopular={planName === 'Professional'}
        onSelect={handleSelectPlan}
      />
    ))}
  </PricingGrid>

  <ComparisonTable plans={plans} /> {/* Optional */}

  <FAQ /> {/* Optional */}
</PricingPage>
```

---

## Styling Guidelines

### 1. **Color Scheme**
- Primary brand color for CTAs and highlights
- Neutral grays for card backgrounds
- Success green for checkmarks and included features
- Accent color for "Most Popular" badge

### 2. **Typography**
- Plan name: Bold, large (24-32px)
- Price: Extra bold, very large (48-64px)
- Description: Regular, medium (16-18px)
- Features: Regular, small-medium (14-16px)

### 3. **Spacing**
- Generous whitespace between cards (24-32px)
- Comfortable padding inside cards (32-48px)
- Consistent spacing between features (12-16px)

### 4. **Animations** (Optional)
- Smooth transition when toggling billing cycles
- Hover effects on cards (subtle lift/shadow)
- Smooth scroll to comparison table

---

## Additional Features to Consider

### 1. **FAQ Section**
Common questions about billing, trials, cancellations, etc.

### 2. **Contact Sales for Enterprise**
Instead of a direct signup, show "Contact Sales" button that opens a form or starts a chat.

### 3. **Social Proof**
- Customer testimonials
- "Join 10,000+ teams" badge
- Trust badges (security, compliance)

### 4. **Feature Tooltips**
Add tooltips to explain technical features (e.g., "What are agent executions?")

### 5. **Currency Selector**
If serving international customers, allow currency switching.

### 6. **Trial Countdown**
If user is on trial, show countdown: "13 days left in your trial"

---

## Error Handling

### 1. **Loading State**
```jsx
{loading && <LoadingSpinner />}
```

### 2. **Error State**
```jsx
{error && (
  <ErrorMessage>
    {error}
    <button onClick={retryFetch}>Try Again</button>
  </ErrorMessage>
)}
```

### 3. **Empty State**
```jsx
{!loading && !error && Object.keys(plans).length === 0 && (
  <EmptyState>
    No pricing plans available at the moment.
  </EmptyState>
)}
```

---

## Accessibility (a11y)

1. **Keyboard Navigation:** All interactive elements must be keyboard accessible
2. **ARIA Labels:** Add appropriate ARIA labels to buttons and toggles
3. **Screen Reader Support:** Ensure prices and features are properly announced
4. **Focus Indicators:** Clear focus states for keyboard users
5. **Color Contrast:** Ensure text meets WCAG AA standards (4.5:1 ratio)

---

## Performance Optimization

1. **Lazy Loading:** Load comparison table only when scrolled into view
2. **Memoization:** Memoize price calculations to avoid unnecessary re-renders
3. **Image Optimization:** Optimize any plan icons or images
4. **Code Splitting:** Split pricing page code if it's large

---

## Testing Checklist

- [ ] Plans load correctly from API
- [ ] Billing toggle switches between monthly and annual
- [ ] Prices update correctly when toggling
- [ ] Savings badges show correct percentages
- [ ] "Most Popular" badge displays on correct plan
- [ ] All features display correctly
- [ ] Null values show as "Unlimited"
- [ ] Mobile responsive design works
- [ ] Tablet responsive design works
- [ ] Desktop layout displays properly
- [ ] CTA buttons work correctly
- [ ] Loading state displays
- [ ] Error state displays
- [ ] Keyboard navigation works
- [ ] Screen reader accessibility

---

## Sample Data for Development

If API is not ready, use this mock data:

```javascript
const mockPlans = {
  "Starter": [
    {
      id: "1",
      name: "Starter",
      description: "Perfect for individuals and small teams",
      tier: "starter",
      final_price: "29.00",
      included_executions: 1000,
      max_users: 3,
      max_agents: 5,
      max_basic_agents: 5,
      storage_gb: 10,
      trial_days: 14,
      features: [
        "1,000 agent executions/month",
        "Up to 5 Basic agents",
        "10GB storage",
        "Email support",
        "Basic analytics"
      ],
      highlight_features: ["14-day free trial"],
      billing_cycle: {
        code: "monthly",
        name: "Monthly",
        months: 1,
        discount_percentage: "0.00"
      }
    }
    // ... add annual variant
  ]
  // ... add Professional and Enterprise
};
```

---

## Next Steps After Implementation

1. **A/B Testing:** Test different pricing displays, button text, and layouts
2. **Analytics:** Track which plans users select, bounce rates, conversion rates
3. **User Feedback:** Collect feedback on pricing clarity and value proposition
4. **Iterate:** Adjust based on data and feedback

---

## Support Resources

- **API Documentation:** https://api.obsolio.com/api/documentation
- **Design System:** [Link to your design system/Figma]
- **Support:** [Your support contact]

---

## Summary

Build a clean, modern pricing page that:
1. ✅ Fetches plans from `/api/v1/pricing/plans`
2. ✅ Displays plans in a responsive grid
3. ✅ Allows toggling between monthly/annual billing
4. ✅ Shows savings for annual plans
5. ✅ Highlights the most popular plan
6. ✅ Displays features clearly
7. ✅ Handles null values as "Unlimited"
8. ✅ Is fully responsive and accessible
9. ✅ Has proper loading and error states
10. ✅ Includes clear CTAs for each plan

**Goal:** Make it easy for users to understand the value of each plan and confidently select the right one for their needs.

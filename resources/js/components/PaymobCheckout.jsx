import React, { useState, useEffect } from 'react';
import axios from 'axios';

const PaymobCheckout = ({ plan, billingCycle, user, onPaymentSuccess, onPaymentError }) => {
    const [loading, setLoading] = useState(false);
    const [iframeUrl, setIframeUrl] = useState(null);
    const [error, setError] = useState(null);

    const initiatePayment = async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await axios.post('/api/v1/payments/subscription', {
                plan_id: plan.id,
                billing_cycle: billingCycle,
            }, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('token')}`, // Adjust auth header usage as per your app
                }
            });

            if (response.data.success) {
                setIframeUrl(response.data.data.iframe_url);
            } else {
                setError('Failed to initiate payment');
                if (onPaymentError) onPaymentError('Failed to initiate payment');
            }
        } catch (err) {
            console.error('Payment initialization error:', err);
            const errorMessage = err.response?.data?.message || err.message || 'An error occurred';
            setError(errorMessage);
            if (onPaymentError) onPaymentError(errorMessage);
        } finally {
            setLoading(false);
        }
    };

    // Listen for Paymob callbacks or handle iframe events if possible
    // Note: Paymob usually redirects within the iframe or parent window.
    // If redirecting parent, the app needs to handle the return URL.

    useEffect(() => {
        if (plan && billingCycle) {
            initiatePayment();
        }
    }, [plan, billingCycle]);

    if (loading) {
        return (
            <div className="flex justify-center items-center p-8">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
                <span className="ml-3 text-gray-600">Initializing Secure Payment...</span>
            </div>
        );
    }

    if (error) {
        return (
            <div className="p-4 bg-red-50 border border-red-200 rounded-md text-red-600 text-center">
                <p>{error}</p>
                <button 
                    onClick={initiatePayment}
                    className="mt-4 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition"
                >
                    Retry Payment
                </button>
            </div>
        );
    }

    if (iframeUrl) {
        return (
            <div className="w-full h-screen max-h-[800px] border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                <iframe
                    src={iframeUrl}
                    className="w-full h-full border-0"
                    title="Paymob Checkout"
                    allow="payment"
                />
            </div>
        );
    }

    return null;
};

export default PaymobCheckout;

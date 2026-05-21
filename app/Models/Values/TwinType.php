<?php

namespace App\Models\Values;

enum TwinType: string
{
    case Product = 'product';
    case Account = 'account';
    case Customer = 'customer';
    case Subscription = 'subscription';
    case Transaction = 'transaction';
    // charge is not a transaction?
    // payment is different
    case Invoice = 'invoice';
    case Price = 'price';
    case Checkout = 'checkout';
    case SetupIntent = 'setup_intent';
    case PaymentIntent = 'payment_intent';
    case Source = 'source';

    case PaymentMethod = 'payment_method';

}

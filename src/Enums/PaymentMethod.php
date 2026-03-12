<?php

namespace Sarkhanrasimoghlu\KapitalBank\Enums;

enum PaymentMethod: string
{
    case Card = 'card';
    case Wallet = 'wallet';
}

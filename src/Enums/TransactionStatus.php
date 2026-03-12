<?php

namespace Sarkhanrasimoghlu\KapitalBank\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Canceled = 'canceled';
    case WaitingForCapture = 'waiting_for_capture';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
}

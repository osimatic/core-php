<?php

namespace Osimatic\Invoice;

/**
 * The status of an invoice's submission to Chorus Pro, tracked on the invoice for follow-up and manual recovery in case of error.
 */
enum ChorusProSubmissionStatus: string
{
	case PENDING = 'PENDING';
	case SUBMITTED = 'SUBMITTED';
	case ACCEPTED = 'ACCEPTED';
	case REJECTED = 'REJECTED';
	case ERROR = 'ERROR';
}
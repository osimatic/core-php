<?php

namespace Osimatic\Invoice;

/**
 * The status of an invoice's submission to Chorus Pro, as returned by ChorusProClient::submit() and meant to be stored by the caller for follow-up and manual recovery in case of error.
 * NOT_APPLICABLE and DISABLED are outcomes of a submit() call where nothing was sent, and are not meant to be stored.
 */
enum ChorusProSubmissionStatus: string
{
	// Nothing was sent: the invoice is not addressed to a Chorus Pro recipient
	case NOT_APPLICABLE = 'NOT_APPLICABLE';

	// Nothing was sent: the Chorus Pro feature is disabled on the client
	case DISABLED = 'DISABLED';

	// Submission created but not sent yet (e.g. queued by the caller for a deferred send)
	case PENDING = 'PENDING';

	// The invoice was successfully sent to Chorus Pro, which returned an identifier
	case SUBMITTED = 'SUBMITTED';

	// The invoice was accepted by the recipient (status followed up via ChorusProClient::getInvoiceStatus())
	case ACCEPTED = 'ACCEPTED';

	// The invoice was rejected by the recipient or by Chorus Pro (status followed up via ChorusProClient::getInvoiceStatus())
	case REJECTED = 'REJECTED';

	// The submission failed (invalid invoice, authentication failure, API error, etc.), the error message is available for manual follow-up
	case ERROR = 'ERROR';
}

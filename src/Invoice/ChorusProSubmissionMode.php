<?php

namespace Osimatic\Invoice;

/**
 * The Chorus Pro invoice submission mode ("modeDepot"), determining what content is sent to the "soumettreFacture" API endpoint.
 * @link https://communaute.chorus-pro.gouv.fr/retrieve-submission-mode/?lang=en Chorus Pro submission modes documentation
 */
enum ChorusProSubmissionMode: string
{
	// Invoice fields sent as plain structured JSON, no file at all
	case SAISIE_API = 'SAISIE_API';

	// Structured CII XML flux, no PDF
	case EDI_XML_STRUCT = 'EDI_XML_STRUCT';

	// Factur-X hybrid PDF/A-3 file, base64-encoded
	case DEPOT_PDF_API = 'DEPOT_PDF_API';
}
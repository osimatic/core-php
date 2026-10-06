<?php

namespace Osimatic\Bank;

/**
 * Enumeration of the 5-digit response codes returned by PayBox/Verifone (CODEREPONSE parameter).
 * Codes in the 00000-00099 range are PayBox platform codes, codes in the 00100-00199 range are card issuer (Visa) codes.
 * Code 00000 indicates a successful operation.
 * @link https://www.paybox.com/ PayBox documentation
 */
enum PayBoxResponseCode: string
{
	// ========================================
	// PayBox platform codes (00000-00099)
	// ========================================

	/** Operation successful */
	case SUCCESS = '00000';

	/** Connection to the authorization center failed */
	case AUTHORIZATION_CENTER_CONNECTION_FAILED = '00001';

	/** A consistency error occurred */
	case CONSISTENCY_ERROR = '00002';

	/** PayBox error */
	case PAYBOX_ERROR = '00003';

	/** Invalid card number or visual cryptogram */
	case INVALID_CARD_NUMBER_OR_CVV = '00004';

	/** Invalid question number */
	case INVALID_QUESTION_NUMBER = '00005';

	/** Access denied or incorrect site/rank/identifier */
	case ACCESS_DENIED = '00006';

	/** Invalid date */
	case INVALID_DATE = '00007';

	/** Incorrect expiration date */
	case INVALID_EXPIRATION_DATE = '00008';

	/** Invalid operation type */
	case INVALID_OPERATION_TYPE = '00009';

	/** Unknown currency */
	case UNKNOWN_CURRENCY = '00010';

	/** Incorrect amount */
	case INVALID_AMOUNT = '00011';

	/** Invalid order reference */
	case INVALID_ORDER_REFERENCE = '00012';

	/** This version is no longer supported */
	case UNSUPPORTED_VERSION = '00013';

	/** Inconsistent frame received */
	case INCONSISTENT_FRAME = '00014';

	/** Error accessing previously referenced data */
	case REFERENCED_DATA_ACCESS_ERROR = '00015';

	/** Subscriber already exists (new subscriber registration) */
	case SUBSCRIBER_ALREADY_EXISTS = '00016';

	/** Subscriber does not exist */
	case SUBSCRIBER_NOT_FOUND = '00017';

	/** Transaction not found */
	case TRANSACTION_NOT_FOUND = '00018';

	/** Visual cryptogram not present */
	case CVV_MISSING = '00020';

	/** Card not authorized */
	case CARD_NOT_AUTHORIZED = '00021';

	/** Limit reached */
	case LIMIT_REACHED = '00022';

	/** Cardholder already used the service today */
	case CARDHOLDER_ALREADY_USED_TODAY = '00023';

	/** Country code filtered for this merchant */
	case COUNTRY_CODE_FILTERED = '00024';

	/** Incorrect activity code */
	case INVALID_ACTIVITY_CODE = '00026';

	/** Cardholder enrolled but not authenticated */
	case CARDHOLDER_ENROLLED_NOT_AUTHENTICATED = '00040';

	/** Connection timeout reached */
	case CONNECTION_TIMEOUT = '00097';

	/** Internal connection error */
	case INTERNAL_CONNECTION_ERROR = '00098';

	/** Inconsistency between the question and the answer, retry later */
	case QUESTION_ANSWER_MISMATCH = '00099';

	// ========================================
	// Card issuer (Visa) codes (00100-00199)
	// ========================================

	/** Transaction approved or successfully processed */
	case APPROVED = '00100';

	/** Contact the card issuer */
	case CONTACT_CARD_ISSUER = '00101';

	/** Contact the card issuer (special conditions referral) */
	case CONTACT_CARD_ISSUER_REFERRAL = '00102';

	/** Invalid merchant */
	case INVALID_MERCHANT = '00103';

	/** Keep the card */
	case KEEP_CARD = '00104';

	/** Do not honor */
	case DO_NOT_HONOR = '00105';

	/** Keep the card, special conditions */
	case KEEP_CARD_SPECIAL_CONDITIONS = '00107';

	/** Approve after cardholder identification */
	case APPROVE_AFTER_CARDHOLDER_IDENTIFICATION = '00108';

	/** Invalid transaction */
	case INVALID_TRANSACTION = '00112';

	/** Invalid transaction amount */
	case INVALID_TRANSACTION_AMOUNT = '00113';

	/** Invalid cardholder number */
	case INVALID_CARDHOLDER_NUMBER = '00114';

	/** Unknown card issuer */
	case UNKNOWN_CARD_ISSUER = '00115';

	/** Customer cancellation */
	case CUSTOMER_CANCELLATION = '00117';

	/** Retry the transaction later */
	case RETRY_TRANSACTION_LATER = '00119';

	/** Erroneous response (error in the server domain) */
	case ERRONEOUS_RESPONSE = '00120';

	/** File update not supported */
	case FILE_UPDATE_NOT_SUPPORTED = '00124';

	/** Unable to locate the record in the file */
	case FILE_RECORD_NOT_FOUND = '00125';

	/** Duplicate record, old record replaced */
	case DUPLICATE_FILE_RECORD = '00126';

	/** Edit error on a file update field */
	case FILE_UPDATE_FIELD_EDIT_ERROR = '00127';

	/** File access forbidden */
	case FILE_ACCESS_FORBIDDEN = '00128';

	/** File update impossible */
	case FILE_UPDATE_IMPOSSIBLE = '00129';

	/** Format error */
	case FORMAT_ERROR = '00130';

	/** Unknown acquirer identifier */
	case UNKNOWN_ACQUIRER_ID = '00131';

	/** Card expiration date exceeded */
	case CARD_EXPIRED = '00133';

	/** Suspected fraud */
	case FRAUD_SUSPECTED = '00134';

	/** Number of PIN attempts exceeded */
	case PIN_TRIES_EXCEEDED = '00138';

	/** Lost card */
	case LOST_CARD = '00141';

	/** Stolen card */
	case STOLEN_CARD = '00143';

	/** Insufficient funds or credit limit exceeded */
	case INSUFFICIENT_FUNDS = '00151';

	/** Card expiration date exceeded */
	case CARD_VALIDITY_DATE_EXCEEDED = '00154';

	/** Incorrect PIN */
	case INCORRECT_PIN = '00155';

	/** Card not found in the file */
	case CARD_NOT_IN_FILE = '00156';

	/** Transaction not permitted to this cardholder */
	case TRANSACTION_NOT_PERMITTED_TO_CARDHOLDER = '00157';

	/** Transaction not permitted at the terminal */
	case TRANSACTION_NOT_PERMITTED_AT_TERMINAL = '00158';

	/** Suspected fraud */
	case FRAUD_SUSPICION = '00159';

	/** The card acceptor must contact the acquirer */
	case ACCEPTOR_MUST_CONTACT_ACQUIRER = '00160';

	/** Withdrawal amount limit exceeded */
	case WITHDRAWAL_LIMIT_EXCEEDED = '00161';

	/** Security rules not respected */
	case SECURITY_RULES_NOT_RESPECTED = '00163';

	/** Response not received or received too late */
	case RESPONSE_NOT_RECEIVED_OR_TOO_LATE = '00168';

	/** Number of PIN attempts exceeded */
	case PIN_ATTEMPTS_EXCEEDED = '00175';

	/** Cardholder already opposed, old record kept */
	case CARDHOLDER_ALREADY_OPPOSED = '00176';

	/** Authentication failed */
	case AUTHENTICATION_FAILED = '00189';

	/** System temporarily stopped */
	case SYSTEM_TEMPORARILY_UNAVAILABLE = '00190';

	/** Card issuer unreachable */
	case CARD_ISSUER_UNREACHABLE = '00191';

	/** Duplicate request */
	case DUPLICATE_REQUEST = '00194';

	/** System malfunction */
	case SYSTEM_MALFUNCTION = '00196';

	/** Global monitoring timeout expired */
	case GLOBAL_MONITORING_TIMEOUT = '00197';

	/** Server unreachable (set by the server) */
	case SERVER_UNREACHABLE = '00198';

	/** Initiator domain incident */
	case INITIATOR_DOMAIN_INCIDENT = '00199';

	// ========================================
	// Methods
	// ========================================

	/**
	 * Checks whether the code indicates a successful operation.
	 * @return bool True only for SUCCESS (00000)
	 */
	public function isSuccess(): bool
	{
		return self::SUCCESS === $this;
	}

	/**
	 * Checks whether the code is a card issuer (Visa) code, as opposed to a PayBox platform code.
	 * Card issuer codes are in the 00100-00199 range.
	 * @return bool True if the code is in the 00100-00199 range
	 */
	public function isCardIssuerCode(): bool
	{
		return (int) $this->value >= 100;
	}

	/**
	 * Gets the localized message describing the response code.
	 * Returns the French message as specified by the PayBox API, for display or logging purposes.
	 * @return string The localized message
	 */
	public function getMessage(): string
	{
		return match ($this) {
			self::SUCCESS => 'Opération réussie',
			self::AUTHORIZATION_CENTER_CONNECTION_FAILED => 'Echec de connexion au centre d’autorisation',
			self::CONSISTENCY_ERROR => 'Une erreur de cohérence est survenue',
			self::PAYBOX_ERROR => 'Erreur Paybox',
			self::INVALID_CARD_NUMBER_OR_CVV => 'Numéro de porteur ou cryptogramme visuel invalide',
			self::INVALID_QUESTION_NUMBER => 'Numéro de question invalide',
			self::ACCESS_DENIED => 'Accès refusé ou site/rang/identifiant incorrect',
			self::INVALID_DATE => 'Date invalide',
			self::INVALID_EXPIRATION_DATE => 'Date de fin de validité incorrecte',
			self::INVALID_OPERATION_TYPE => 'Type d’opération invalide.',
			self::UNKNOWN_CURRENCY => 'Devise inconnue',
			self::INVALID_AMOUNT => 'Montant incorrect',
			self::INVALID_ORDER_REFERENCE => 'Référence commande invalide',
			self::UNSUPPORTED_VERSION => 'Cette version n’est plus soutenue',
			self::INCONSISTENT_FRAME => 'Trame reçue incohérente',
			self::REFERENCED_DATA_ACCESS_ERROR => 'Erreur d’accès aux données précédemment référencées.',
			self::SUBSCRIBER_ALREADY_EXISTS => 'Abonné déjà existant (inscription nouvel abonné)',
			self::SUBSCRIBER_NOT_FOUND => 'Abonné inexistant.',
			self::TRANSACTION_NOT_FOUND => 'Transaction non trouvée',
			self::CVV_MISSING => 'Cryptogramme visuel non présent',
			self::CARD_NOT_AUTHORIZED => 'Carte non autorisée',
			self::LIMIT_REACHED => 'Plafond atteint',
			self::CARDHOLDER_ALREADY_USED_TODAY => 'Porteur déjà passé aujourd’hui',
			self::COUNTRY_CODE_FILTERED => 'Code pays filtré pour ce commerçant',
			self::INVALID_ACTIVITY_CODE => 'Code activité incorrect',
			self::CARDHOLDER_ENROLLED_NOT_AUTHENTICATED => 'Porteur enrôlé mais non authentifié',
			self::CONNECTION_TIMEOUT => 'Timeout de connexion atteint',
			self::INTERNAL_CONNECTION_ERROR => 'Erreur de connexion interne',
			self::QUESTION_ANSWER_MISMATCH => 'Incohérence entre la question et la réponse. Refaire une nouvelle tentative ultérieurement',

			self::APPROVED => 'Transaction approuvée ou traitée avec succès',
			self::CONTACT_CARD_ISSUER, self::CONTACT_CARD_ISSUER_REFERRAL => 'Contacter l’émetteur de carte',
			self::INVALID_MERCHANT => 'Commerçant invalide',
			self::KEEP_CARD => 'Conserver la carte',
			self::DO_NOT_HONOR => 'Ne pas honorer',
			self::KEEP_CARD_SPECIAL_CONDITIONS => 'Conserver la carte, conditions spéciales',
			self::APPROVE_AFTER_CARDHOLDER_IDENTIFICATION => 'Approuver après identification du porteur',
			self::INVALID_TRANSACTION => 'Transaction invalide',
			self::INVALID_TRANSACTION_AMOUNT => 'Montant invalide',
			self::INVALID_CARDHOLDER_NUMBER => 'Numéro de porteur invalide',
			self::UNKNOWN_CARD_ISSUER => 'Emetteur de carte inconnu',
			self::CUSTOMER_CANCELLATION => 'Annulation client',
			self::RETRY_TRANSACTION_LATER => 'Répéter la transaction ultérieurement',
			self::ERRONEOUS_RESPONSE => 'Réponse erronée (erreur dans le domaine serveur)',
			self::FILE_UPDATE_NOT_SUPPORTED => 'Mise à jour de fichier non supportée',
			self::FILE_RECORD_NOT_FOUND => 'Impossible de localiser l’enregistrement dans le fichier',
			self::DUPLICATE_FILE_RECORD => 'Enregistrement dupliqué, ancien enregistrement remplacé',
			self::FILE_UPDATE_FIELD_EDIT_ERROR => 'Erreur en « edit » sur champ de mise à jour fichier',
			self::FILE_ACCESS_FORBIDDEN => 'Accès interdit au fichier',
			self::FILE_UPDATE_IMPOSSIBLE => 'Mise à jour de fichier impossible',
			self::FORMAT_ERROR => 'Erreur de format',
			self::UNKNOWN_ACQUIRER_ID => 'Identifiant de l’organisme acquéreur inconnu.',
			self::CARD_EXPIRED => 'Date de validité de la carte dépassée.',
			self::FRAUD_SUSPECTED => 'Suspicion de fraude.',
			self::PIN_TRIES_EXCEEDED, self::PIN_ATTEMPTS_EXCEEDED => 'Nombre d’essais code confidentiel dépassé',
			self::LOST_CARD => 'Carte perdue',
			self::STOLEN_CARD => 'Carte volée',
			self::INSUFFICIENT_FUNDS => 'Provision insuffisante ou crédit dépassé',
			self::CARD_VALIDITY_DATE_EXCEEDED => 'Date de validité de la carte dépassée',
			self::INCORRECT_PIN => 'Code confidentiel erroné',
			self::CARD_NOT_IN_FILE => 'Carte absente du fichier',
			self::TRANSACTION_NOT_PERMITTED_TO_CARDHOLDER => 'Transaction non permise à ce porteur',
			self::TRANSACTION_NOT_PERMITTED_AT_TERMINAL => 'Transaction interdite au terminal',
			self::FRAUD_SUSPICION => 'Suspicion de fraude',
			self::ACCEPTOR_MUST_CONTACT_ACQUIRER => 'L’accepteur de carte doit contacter l’acquéreur',
			self::WITHDRAWAL_LIMIT_EXCEEDED => 'Dépasse la limite du montant de retrait',
			self::SECURITY_RULES_NOT_RESPECTED => 'Règles de sécurité non respectées',
			self::RESPONSE_NOT_RECEIVED_OR_TOO_LATE => 'Réponse non parvenue ou reçue trop tard',
			self::CARDHOLDER_ALREADY_OPPOSED => 'Porteur déjà en opposition, ancien enregistrement conservé',
			self::AUTHENTICATION_FAILED => 'Echec de l’authentification',
			self::SYSTEM_TEMPORARILY_UNAVAILABLE => 'Arrêt momentané du système',
			self::CARD_ISSUER_UNREACHABLE => 'Emetteur de cartes inaccessible',
			self::DUPLICATE_REQUEST => 'Demande dupliquée',
			self::SYSTEM_MALFUNCTION => 'Mauvais fonctionnement du système',
			self::GLOBAL_MONITORING_TIMEOUT => 'Echéance de la temporisation de surveillance globale',
			self::SERVER_UNREACHABLE => 'Serveur inaccessible (positionné par le serveur).',
			self::INITIATOR_DOMAIN_INCIDENT => 'Incident domaine initiateur.',
		};
	}

	/**
	 * Parses a raw PayBox response code string into the corresponding enum value.
	 * Returns null for null, empty or unknown codes, so raw API responses can be safely parsed.
	 * @param string|null $responseCode The 5-digit response code (e.g., '00000')
	 * @return self|null The corresponding enum, or null if input is null, empty or unknown
	 */
	public static function parse(?string $responseCode): ?self
	{
		if (null === $responseCode) {
			return null;
		}

		return self::tryFrom(trim($responseCode));
	}
}
<?php

if (!defined('_ECRIRE_INC_VERSION')) return;

$GLOBALS[$GLOBALS['idx_lang']] = array(

	'label_regler_facture_numero' => '<b>Numéro</b> de la facture',
	'label_regler_facture_montant' => '<b>Montant TTC</b> de la facture',
	'bouton_regler' => 'Payer cette facture',

	'erreur_indiquez_numero_facture' => 'Indiquez le <b>Numéro</b> situé en haut à droite de la facture',
	'erreur_format_numero_facture' => 'Ce numéro de facture n\'est pas valide',
	'erreur_indiquez_montant_facture' => 'Indiquez le <b>Montant total TTC en euros</b> situé en bas à droite de la facture',
	'erreur_numero_montant_facture_incoherents' => 'Le numéro et le montant de la facture ne correspondent pas à une facture existante. Vérifiez les informations que vous avez saisies.',
	'erreur_facture_deja_payee' => 'Cette facture est déjà payée, il n\'y a rien à faire',
	'explication_regler_facture_numero' => 'Exemple : <tt>FA1801-1234</tt>',
	'explication_regler_facture_montant' => 'Exemple : <tt>123.45</tt>',

	'erreur_import_facture_dolibarr' => 'Une erreur technique est survenue lors de l\'import de cette facture pour paiement.',
	'erreur_technique_creation_transaction' => 'Une erreur technique est survenue au moment de la creation de la transaction.',

	'info_reglement_recu' => 'Règlement reçu le ',
	'titre_reglement_facture' => 'Règlement facture @ref@',

);


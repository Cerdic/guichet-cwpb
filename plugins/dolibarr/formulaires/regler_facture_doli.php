<?php

if (!defined("_ECRIRE_INC_VERSION")) return;

function formulaires_regler_facture_doli_charger_dist() {

	$valeurs = array(
		'regler_facture_numero' => '',
		'regler_facture_montant' => '',
	);

	return $valeurs;
}

function formulaires_regler_facture_doli_verifier_dist() {

	$erreurs = array();

	$numero = trim(_request('regler_facture_numero'));
	if (!$numero) {
		$erreurs['regler_facture_numero'] = _T('regler:erreur_indiquez_numero_facture');
	}
	else {
		$numero = normaliser_saisie_numero($numero);
		set_request('regler_facture_numero', $numero);
		$year = date('y');
		$month = date('m');
		if (!preg_match(',^FA(\d\d)(\d\d)-\d+$,', $numero, $m)
		  or intval($m[1])<$year-1 or intval($m[1])>$year
		  or intval($m[2])<1 or intval($m[2])>12
		  or (intval($m[1])==$year and intval($m[2])>$month)) {
			$erreurs['regler_facture_numero'] = _T('regler:erreur_format_numero_facture');
		}
	}

	$montant = trim(_request('regler_facture_montant'));
	if (!$montant) {
		$erreurs['regler_facture_montant'] = _T('regler:erreur_indiquez_montant_facture');
	}
	else {
		$montant = normaliser_saisie_montant($montant);
		set_request('regler_facture_montant', $montant);
	}

	if (!count($erreurs)) {

		include_spip('inc/dolibarr');
		$facture = dolibarr_recuperer_facture(null, $numero);

		$factid = $facture->id;
		$fact_ttc = $facture->total_ttc;
		$fact_date = date('Y-m-d H:i:s',$facture->date_validation);
		$fact_paye = $facture->paye;

		#$erreurs['message_erreur'] = "$numero : $factid : $fact_ttc : $fact_date : $fact_paye ?";

		// si le montant est faux, on indique que les infos sont incoherentes
		if (intval(floatval($fact_ttc) * 100) !== intval($montant * 100)) {
			$erreurs['message_erreur'] = _T('regler:erreur_numero_montant_facture_incoherents');
			#$erreurs['message_erreur'] .= " $fact_ttc <> $montant";
		}
		elseif($fact_paye) {
			$erreurs['message_ok'] = _T('regler:erreur_facture_deja_payee');
		}

		//var_dump($facture);
	}

	return $erreurs;
}

function formulaires_regler_facture_doli_traiter_dist() {

	$res = array(
		'editable' => true,
	);
	$numero = trim(_request('regler_facture_numero'));
	$numero = normaliser_saisie_numero($numero);

	include_spip('inc/dolibarr');
	$r = dolibarr_importer_facture_en_base_spip(null, $numero);
	if (!$r) {
		$res['message_erreur'] = _T('regler:erreur_import_facture_dolibarr');
	}
	else {
		list($id_facture, $infos_client) = $r;
		if ($id_transaction = inserer_transaction_selon_facture($id_facture, $infos_client)) {
			$transaction_hash = sql_getfetsel('transaction_hash','spip_transactions','id_transaction='.intval($id_transaction));
			$redirect = generer_url_public('payer-facture',"id_transaction=$id_transaction&transaction_hash=$transaction_hash",true);
			$res['redirect'] = $redirect;
		}
		else {
			$res['message_erreur'] = _T('regler:erreur_technique_creation_transaction');
		}
	}

	return $res;
}


function normaliser_saisie_montant($montant) {
	$montant = str_replace(' ','', $montant);
	$montant = str_replace('€','', $montant);
	$montant = str_replace(',','.', $montant);
	$montant = floatval(trim($montant));

	return $montant;
}

function normaliser_saisie_numero($numero) {
	$numero = trim($numero);
	$numero = strtoupper($numero);

	return $numero;
}

function inserer_transaction_selon_facture($id_facture, $infos=[]) {
	if (!$id_facture or !$facture = sql_fetsel('*','spip_factures', 'id_facture='.intval($id_facture))) {
		return false;
	}
	// d'abord on nettoie les eventuelles transactions echouees sur cette facture
	sql_updateq('spip_transactions', array('id_facture' => 0), 'id_facture='.intval($id_facture).' AND '.sql_in('statut', array('commande','ok'), 'NOT'));

	$inserer_transaction = charger_fonction('inserer_transaction', 'bank');
	$montant = $facture['montant'];
	$options = array(
		'id_auteur' => $facture['id_auteur'],
		'montant_ht' => $facture['montant_ht'],
		'parrain' => $facture['parrain'],
		'tracking_id' => $facture['tracking_id'],
		'auteur' => $facture['client'],
		'force' => false, // recuperer une transaction en commande sur cette facture si elle existe
		'champs' => array(
			'id_facture' => $id_facture,
			'contenu' => json_encode($infos),
		),
	);
	$id_transaction = $inserer_transaction($montant, $options);

	return $id_transaction;

}
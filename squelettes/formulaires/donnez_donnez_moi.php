<?php

if (!defined("_ECRIRE_INC_VERSION")) return;

include_spip('formulaires/regler_facture_doli');

function formulaires_donnez_donnez_moi_charger_dist($montant='', $raison='', $mobile = false) {

	$valeurs = array(
		'name' => '',
		'email' => '',
		'mobile' => '',
		'montant_don' => $montant,
		'raison' => $raison,
		'_demander_mobile' => $mobile ? ' ' : '',
	);

	return $valeurs;
}

function formulaires_donnez_donnez_moi_verifier_dist($montant='', $raison='', $mobile = false) {

	$erreurs = array();

	$oblis = ['name', 'email', 'montant_don', 'raison'];
	if ($mobile) {
		$oblis[] = 'mobile';
	}
	foreach ($oblis as $obli) {
		if (is_null($v = _request($obli)) or !strlen(trim($v))) {
			$erreurs[$obli] = _T('info_obligatoire');
		}
	}

	if ($montant = trim(_request('montant_don'))) {
		$montant = normaliser_saisie_montant($montant);
		set_request('montant_don', $montant);
	}

	return $erreurs;
}

function formulaires_donnez_donnez_moi_traiter_dist($montant='', $raison='', $mobile = false) {

	$res = array(
		'editable' => true,
	);

	$name = _request('name');
	$email = _request('email');
	$montant = _request('montant_don');
	$raison = _request('raison');

	if ($id_transaction = inserer_transaction_don($name, $email, $montant, $raison, $mobile ? _request('mobile') : '')) {
		$transaction_hash = sql_getfetsel('transaction_hash','spip_transactions','id_transaction='.intval($id_transaction));
		$redirect = generer_url_public('payer-don',"id_transaction=$id_transaction&transaction_hash=$transaction_hash",true);
		$res['redirect'] = $redirect;
	}
	else {
		$res['message_erreur'] = _T('regler:erreur_technique_creation_transaction');
	}

	return $res;
}


function inserer_transaction_don($name, $email, $montant, $raison, $mobile = '') {
	$inserer_transaction = charger_fonction('inserer_transaction', 'bank');
	$options = array(
		'montant_ht' => $montant,
		'parrain' => 'don',
		'auteur' => trim("$name $email"),
		'champs' => array(
			'contenu' => trim("DON $raison " . ($mobile ? " | Tel : $mobile" : '')),
		),
	);
	$id_transaction = $inserer_transaction($montant, $options);

	return $id_transaction;
}
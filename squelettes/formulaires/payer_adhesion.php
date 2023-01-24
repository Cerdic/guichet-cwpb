<?php

if (!defined("_ECRIRE_INC_VERSION")) return;

include_spip('formulaires/regler_facture_doli');

function formulaires_payer_adhesion_charger_dist() {

	$valeurs = array(
		'name' => '',
		'email' => '',
		'type_adhesion' => '',
		'recu' => '',
		'adresse_recu' => '',
	);

	return $valeurs;
}

function formulaires_payer_adhesion_verifier_dist() {

	$erreurs = array();

	$oblis = ['name', 'email', 'type_adhesion'];
	if (_request('recu') === 'oui') {
		$oblis[] = 'adresse_recu';
	}
	foreach ($oblis as $obli) {
		if (is_null($v = _request($obli)) or !strlen(trim($v))) {
			$erreurs[$obli] = _T('info_obligatoire');
		}
	}

	if (empty($erreurs['email'])) {
		include_spip('inc/filtres');
		if (!$email = _request('email')
			or !($email = email_valide($email))
			or strpos($email, '@') === false) {
			$erreurs['email'] = _T('info_email_invalide');
		}
	}

	return $erreurs;
}

function formulaires_payer_adhesion_traiter_dist() {

	$res = array(
		'editable' => true,
	);

	$name = _request('name');
	$email = _request('email');
	$type_adhesion = _request('type_adhesion');

	$infos = [
		'nom' => $name,
		'email' => $email,
		'recu' => _request('recu'),
		'adresse_recu' => _request('adresse_recu'),
	];
	$montant = montant_adhesion($type_adhesion);

	if ($id_transaction = inserer_transaction_adhesion($name, $email, $montant, $infos)) {
		$transaction_hash = sql_getfetsel('transaction_hash','spip_transactions','id_transaction='.intval($id_transaction));
		$redirect = generer_url_public('payer-adhesion',"id_transaction=$id_transaction&transaction_hash=$transaction_hash",true);
		$res['redirect'] = $redirect;
	}
	else {
		$res['message_erreur'] = _T('regler:erreur_technique_creation_transaction');
	}

	return $res;
}

function montant_adhesion($type_adhesion) {
	$montants = [
		'sympathisant' => 5,
		'stagiaire' => 15,
		'defaut' => 30,
	];
	if (isset($montants[$type_adhesion])) {
		return $montants[$type_adhesion];
	}
	return $montants['defaut'];
}


function inserer_transaction_adhesion($name, $email, $montant, $infos) {
	$inserer_transaction = charger_fonction('inserer_transaction', 'bank');
	$options = array(
		'montant_ht' => $montant,
		'parrain' => 'adhesion',
		'auteur' => "$name $email",
		'champs' => array(
			'contenu' => json_encode($infos),
		),
	);
	$id_transaction = $inserer_transaction($montant, $options);

	return $id_transaction;
}
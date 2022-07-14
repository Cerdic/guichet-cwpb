<?php

if (!defined("_ECRIRE_INC_VERSION")) return;


function formulaires_acheter_produit_passage_doli_charger_dist() {

	if (!defined('_PRODUITS_PASSAGE_ACHETABLES')) {
		return false;
	}

	include_spip('inc/dolibarr_cwpb');
	$produits = dolibarr_lister_produits(_PRODUITS_PASSAGE_ACHETABLES);

	$valeurs = array(
		'_produits' => $produits,

		'produit_id' => 0,
		'email' => '',
		'name' => '',
		'adresse1' => '',
		'adresse2' => '',
		'code_postal' => '',
		'ville' => '',
		'pays' => 'FR',
	);

	return $valeurs;
}

function formulaires_acheter_produit_passage_doli_verifier_dist() {
	$erreurs = [];

	$oblis = ['produit_id', 'email', 'name', 'adresse1', 'code_postal', 'ville'];

	foreach ($oblis as $obli) {
		if (is_null($v = _request($obli)) or !strlen(trim($v))) {
			$erreurs[$obli] = _T('info_obligatoire');
		}
	}

	if (empty($erreurs['produit_id'])) {
		include_spip('inc/dolibarr_cwpb');
		$produits = dolibarr_lister_produits(_PRODUITS_PASSAGE_ACHETABLES);
		$produits = array_column($produits, 'id');
		if (!in_array(_request('produit_id'), $produits)) {
			$erreurs['produit_id'] = _T('info_obligatoire');
		}
	}

	if (!count($erreurs)) {
		#$erreurs['message_erreur'] = '?';
	}

	return $erreurs;
}


function formulaires_acheter_produit_passage_doli_traiter_dist() {
	$res = [];

	$id_produit = _request('produit_id');
	$nom = _request('name');
	$email = _request('email');
	$infos_client = [
		'name' => $nom,
		'adresse_1' => _request('adresse1'),
		'adresse_2' => _request('adresse2'),
		'adresse_cp' => _request('code_postal'),
		'adresse_ville' => _request('ville'),
		'adresse_pays' => _request('pays'),
	];

	include_spip('inc/dolibarr_clients');
	if (!$id_auteur = dolibarr_trouver_ou_creer_client($nom, $email, $infos_client)) {
		$res['message_erreur'] = _T('acheter:erreur_creation_compte');
	}
	else {
		$produits = dolibarr_lister_produits(_PRODUITS_PASSAGE_ACHETABLES);
		if ($id_transaction = inserer_transaction_achat_produits_dolibarr($id_auteur, [$id_produit], $produits)) {
			$transaction_hash = sql_getfetsel('transaction_hash','spip_transactions','id_transaction='.intval($id_transaction));
			$redirect = generer_url_public('payer-achat',"id_transaction=$id_transaction&transaction_hash=$transaction_hash",true);
			$res['redirect'] = $redirect;
		}
		else {
			$res['message_erreur'] = _T('regler:erreur_technique_creation_transaction');
			$res['editable'] = true;
		}
	}

	return $res;
}


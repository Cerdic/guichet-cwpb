<?php

if (!defined("_ECRIRE_INC_VERSION")) return;

include_spip('formulaires/regler_facture_doli');

function formulaires_donnez_donnez_moi_charger_dist($montant='', $raison='', $demander_nom = true, $mobile = false) {

	$valeurs = array(
		'name' => '',
		'email' => '',
		'mobile' => '',
		'montant_don' => $montant,
		'raison' => $raison,
		'_demander_nom' => $demander_nom ? ' ' : '',
		'_demander_mobile' => $mobile ? ' ' : '',
	);

	return $valeurs;
}

function formulaires_donnez_donnez_moi_verifier_dist($montant='', $raison='', $demander_nom = true, $mobile = false) {

	$erreurs = array();

	$oblis = ['email', 'montant_don', 'raison'];
	if ($demander_nom) {
		$obli[] = 'name';
	}
	if ($mobile) {
		$oblis[] = 'mobile';
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
		elseif (!$demander_nom and !_request('name')) {
			if ($name = cwpb_trouver_nom_from_email($email)) {
				set_request('name', $name);
			}
			else {
				if (!count($erreurs)) {
					$erreurs['message_erreur'] = '';
				}
				$erreurs['name'] = _L('On se connait pas encore, peux-tu préciser ton nom ?');
			}
		}
	}

	if ($montant = trim(_request('montant_don'))) {
		$montant = normaliser_saisie_montant($montant);
		set_request('montant_don', $montant);
	}

	return $erreurs;
}

function formulaires_donnez_donnez_moi_traiter_dist($montant='', $raison='', $demander_nom = true, $mobile = false) {

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

function cwpb_trouver_nom_from_email($email) {
	// un email qui a deja fait un don ?
	if ($auteur = sql_getfetsel('auteur', 'spip_transactions', 'statut=\'ok\' AND auteur LIKE '.sql_quote("% $email"), '', 'id_transaction DESC')) {
		$auteur = explode(' ', $auteur);
		if (end($auteur) === $email) {
			array_pop($auteur);
			return implode(' ', $auteur);
		}
	}
	if ($auteur = sql_getfetsel('auteur', 'spip_transactions', 'statut=\'ok\' AND auteur LIKE '.sql_quote("\"email\":\"$email\""), '', 'id_transaction DESC')) {
		if (preg_match(",<!--(.*)-->$,ms", $auteur, $match)
		  and !$infos_client = json_decode($match[1], true)
		  and !empty($infos_client['name'])) {
			return $infos_client['name'];
		}
	}
	return '';
}
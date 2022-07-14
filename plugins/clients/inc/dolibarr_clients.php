<?php
/**
 * Plugin dolibarr
 * (c) 2019 Cedric pour Nursit
 * Licence GPL
 *
 */


if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('inc/dolibarr');


function dolibarr_trouver_ou_creer_client($nom, $email, $infos) {
	$id_auteur = 0;

	if ($auteur = sql_fetsel('*', 'spip_auteurs', 'statut!=\'5poubelle\' AND email='.sql_quote($email), '', 'id_auteur DESC')) {
		$id_auteur = $auteur['id_auteur'];
	}
	else {
		include_spip('action/editer_auteur');
		$set = [
			'statut' => '6forum',
			'email' => $email,
			'nom' => $nom,
		];
		$id_auteur = auteur_inserer('spip', $set);
		$auteur = sql_fetsel('*', 'spip_auteurs', 'id_auteur='.intval($id_auteur));
	}

	$set = [];
	if ($auteur['nom'] !== $nom) {
		$set['nom'] = $auteur['nom'];
	}
	foreach ($infos as $k => $info) {
		if (isset($auteur[$k]) and $auteur[$k] !== $info) {
			$set[$k] = $info;
		}
	}
	if (!empty($set)) {
		include_spip('action/editer_auteur');
		autoriser_exception('modifier', 'auteur', $id_auteur);
		auteur_modifier($id_auteur, $set);
		autoriser_exception('modifier', 'auteur', $id_auteur, false);
	}

	return $id_auteur;
}
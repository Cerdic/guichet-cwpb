<?php
/**
 * Plugin Clients
 * Gestion des comptes clients
 * (c) 2011 Cedric pour Nursit.net
 * Licence GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;


function clients_recuperer_fond($flux){
	if ($flux['args']['fond'] == 'formulaires/editer_auteur'){
		if ($p = strpos($flux['data']['texte'],'<!--extra-->')){
			$complement = recuperer_fond('formulaires/inc-saisie-profil-client',$flux['args']['contexte']);
			$flux['data']['texte'] = substr_replace($flux['data']['texte'],$complement,$p,0);
		}
	}
	return $flux;
}


/**
 * Renseigner les donnees clients facturation liee a une demande de paiement
 * @param $flux
 * @return mixed
 */
function clients_bank_dsp2_renseigner_facturation($flux) {

	$transaction = $flux['args'];
	if (isset($transaction['id_auteur']) and $id_auteur=intval($transaction['id_auteur'])) {

		if ($auteur = sql_fetsel('*', 'spip_auteurs', 'id_auteur='.intval($id_auteur))) {

			$flux['data']['nom'] = $auteur['name'];
			$flux['data']['prenom'] = $auteur['prenom'];
			$flux['data']['email'] = $auteur['email'];
			$adresse = [$auteur['societe'], $auteur['adresse_1'], $auteur['adresse_2'], $auteur['adresse_bp']];
			$adresse = array_filter($adresse);
			$flux['data']['adresse'] = trim(implode("\n", $adresse));
			$flux['data']['ville'] = $auteur['adresse_ville'];
			$flux['data']['code_postal'] = $auteur['adresse_cp'];
			$flux['data']['pays'] = $auteur['adresse_pays'];
		}
	}

	return $flux;
}
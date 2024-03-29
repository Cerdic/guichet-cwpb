<?php
/**
 * Plugin dolibarr
 * (c) 2019 Cedric pour Nursit
 * Licence GPL
 *
 */

/**
 * Renseigner les infos clients pour le paiement CB
 * @param $flux
 * @return mixed
 */
function dolibarr_bank_dsp2_renseigner_facturation($flux) {

	// si c'est une transaction associee a un form
	if ($id_transaction = $flux['args']['id_transaction']
	  AND $flux['args']['parrain'] == 'dolibarr'
	  AND $facid = $flux['args']['tracking_id']){

		if ($flux['args']['contenu'] and $infos = json_decode($flux['args']['contenu'], true)) {
			$flux['data']['nom'] = $infos['name'];
			$flux['data']['code_postal'] = $infos['zip'];
			$flux['data']['pays'] = $infos['state'];
			$flux['data']['email'] = $infos['email'];
		}
		else {
			include_spip("inc/dolibarr");
			if ($facture = dolibarr_recuperer_facture($facid)
				and $socid = $facture->socid) {
				$connexion = dolibarr_connect();
				$db = &$connexion['db'];
				$societe = new Societe($db);
				if ($societe->fetch($socid)) {
					$flux['data']['nom'] = $societe->name;
					$flux['data']['code_postal'] = $societe->zip;
					$flux['data']['pays'] = $societe->country_code;
					$flux['data']['email'] = $societe->email;
				}
			}
		}
	}

	return $flux;
}


/**
 * Creer la facture dans dolibarr au moment de la creation de la facture en base SPIP
 * @param $flux
 * @return mixed
 */
function dolibarr_post_insertion($flux) {

	if ($flux['args']['table'] == 'spip_factures'
	  and $id_facture = $flux['args']['id_objet']) {

		include_spip('inc/dolibarr_generer_facture');
		$facture = $flux['data'];

		if ($transaction = sql_fetsel('*','spip_transactions','id_facture='.intval($id_facture))
		  and $transaction['parrain'] === 'achatdoli'
		  and !empty($transaction['contenu'])
		  and $items = json_decode($transaction['contenu'], true)) {
			$flux['data'] = dolibarr_generer_facture($id_facture, $facture, $items);
		}

	}

	return $flux;
}


/**
 * Marquer la facture doli comme payee
 * @param array $flux
 * @return mixed
 */
function dolibarr_bank_traiter_reglement($flux) {

	if ($id_transaction = $flux['args']['id_transaction']) {
		include_spip('inc/dolibarr_regler_facture');
		dolibarr_regler_facture($id_transaction);
	}

	return $flux;
}

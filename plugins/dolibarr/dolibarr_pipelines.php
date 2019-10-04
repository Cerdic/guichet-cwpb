<?php

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

<?php

define('_DIR_PLUGINS_SUPPL', _DIR_RACINE . "squelettes/plugins/");

function autoriser_transaction_facturer_dist($faire, $type, $id, $qui, $opt) {

	if ($id_transaction = intval($id)
	  and $transaction = sql_fetsel('*', 'spip_transactions', 'id_transaction='.intval($id))) {

		// si c'est un DON, pas de facture
		if ($transaction['parrain'] === 'don') {
			return false;
		}

		// si c'est montant=0 pas de facture
		if (!intval($transaction['montant'] * 100)) {
			return false;
		}

	}

	return true;
}


if (!isset($GLOBALS['spip_pipeline']['bank_dsp2_renseigner_facturation'])) {
	$GLOBALS['spip_pipeline']['bank_dsp2_renseigner_facturation'] = '';
}
$GLOBALS['spip_pipeline']['bank_dsp2_renseigner_facturation'] .= '|guichet_bank_dsp2_renseigner_facturation';
if (!isset($GLOBALS['spip_pipeline']['bank_description_transaction'])) {
	$GLOBALS['spip_pipeline']['bank_description_transaction'] = '';
}
$GLOBALS['spip_pipeline']['bank_description_transaction'] .= '|guichet_bank_description_transaction';

/**
 * Renseigner les infos clients pour le paiement CB
 * @param $flux
 * @return mixed
 */
function guichet_bank_dsp2_renseigner_facturation($flux) {

	// si c'est une transaction associee a un don
	if ($id_transaction = $flux['args']['id_transaction']
	  AND $flux['args']['parrain'] == 'don'
	  AND $auteur = $flux['args']['auteur']){

		$auteur = explode(' ', $auteur);
		$flux['data']['email'] = array_pop($auteur);
		$flux['data']['nom'] = implode(' ', $auteur);
	}

	return $flux;
}

function guichet_bank_description_transaction($flux) {

	// si c'est une transaction associee a un don
	if ($id_transaction = $flux['args']['id_transaction']
	  AND $flux['args']['parrain'] == 'don'
	  AND $raison = $flux['args']['contenu']){
		$flux['data']['libelle'] = $raison;
	}

	return $flux;

}
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
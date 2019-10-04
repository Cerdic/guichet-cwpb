<?php
/**
 * Plugin dolibarr
 * (c) 2019 Cedric pour Nursit
 * Licence GPL
 *
 */


if (!defined('_ECRIRE_INC_VERSION')) return;


/**
 * Numero provisoire pour insertion en base, numero definitif fourni par Dolibarr
 * @param $id_facture
 * @param $date_paiement
 * @return string
 */
function inc_numeroter_facture_dist($id_facture,$date_paiement){
	return "PROV-".$id_facture;
}
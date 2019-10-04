<?php
/*
 * Factures
 * module de facturation
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012 - Distribue sous licence GNU/GPL
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
<?php
/**
 * Plugin Clients
 * Gestion des comptes clients
 * (c) 2011 Cedric pour Nursit.net
 * Licence GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;


/**
 * Verifier que l'on dispose bien des infos legales pour un client
 *
 * @param int $id_auteur
 * @return bool
 */
function clients_verifier_infos_legales($id_auteur){
	$row = sql_fetsel('*','spip_auteurs','id_auteur='.intval($id_auteur));
	if (!$row) return false;
	if (!$row['name']) return false;
	if (!$row['email']) return false;
	if (!$row['adresse_1']) return false;
	if (!$row['adresse_cp']) return false;
	if (!$row['adresse_ville']) return false;
	if (!$row['adresse_pays']) return false;
	return true;
}



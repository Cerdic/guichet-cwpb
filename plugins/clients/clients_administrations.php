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
 * Declaration des champs complementaires sur la table auteurs, pour les clients
 *
 * @param  $tables
 * @return
 */
function clients_declarer_tables_objets_sql($tables){

	$tables['spip_auteurs']['field']['name'] = "text DEFAULT '' NOT NULL";
	$tables['spip_auteurs']['field']['prenom'] = "text DEFAULT '' NOT NULL";
	$tables['spip_auteurs']['field']['societe'] = "text DEFAULT '' NOT NULL";
	$tables['spip_auteurs']['field']['adresse_1'] = "text DEFAULT '' NOT NULL";
	$tables['spip_auteurs']['field']['adresse_2'] = "text DEFAULT '' NOT NULL";
	$tables['spip_auteurs']['field']['adresse_bp'] = "tinytext DEFAULT '' NOT NULL";
	$tables['spip_auteurs']['field']['adresse_cp'] = "tinytext DEFAULT '' NOT NULL";
	$tables['spip_auteurs']['field']['adresse_ville'] = "tinytext DEFAULT '' NOT NULL";
	$tables['spip_auteurs']['field']['adresse_pays'] = "tinytext DEFAULT '' NOT NULL";
	$tables['spip_auteurs']['field']['tel_fixe'] = "tinytext DEFAULT '' NOT NULL";
	$tables['spip_auteurs']['field']['tel_mobile'] = "tinytext DEFAULT '' NOT NULL";

	$tables['spip_auteurs']['field']['dolibarr_socid'] = "tinytext DEFAULT '' NOT NULL";

	$tables['spip_auteurs']['champs_editables'][] = 'name';
	$tables['spip_auteurs']['champs_editables'][] = 'prenom';
	$tables['spip_auteurs']['champs_editables'][] = 'societe';
	$tables['spip_auteurs']['champs_editables'][] = 'adresse_1';
	$tables['spip_auteurs']['champs_editables'][] = 'adresse_2';
	$tables['spip_auteurs']['champs_editables'][] = 'adresse_bp';
	$tables['spip_auteurs']['champs_editables'][] = 'adresse_cp';
	$tables['spip_auteurs']['champs_editables'][] = 'adresse_ville';
	$tables['spip_auteurs']['champs_editables'][] = 'adresse_pays';
	$tables['spip_auteurs']['champs_editables'][] = 'tel_fixe';
	$tables['spip_auteurs']['champs_editables'][] = 'tel_mobile';

	return $tables;
}


/**
 * Installation/maj des tables clients
 *
 * @param string $nom_meta_base_version
 * @param string $version_cible
 */
function clients_upgrade($nom_meta_base_version,$version_cible){
	$maj = array();
	// creation initiale
	$maj['create'] = array(
		array('maj_tables',array('spip_auteurs')),
	);

	// lancer la maj
	include_spip('base/upgrade');
	maj_plugin($nom_meta_base_version, $version_cible, $maj);
}


/**
 * Desinstallation/suppression du plugin
 *
 * @param string $nom_meta_base_version
 */
function clients_vider_tables($nom_meta_base_version) {
	effacer_meta($nom_meta_base_version);
}

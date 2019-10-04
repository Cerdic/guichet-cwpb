<?php
/**
 * Plugin dolibarr
 * (c) 2019 Cedric pour Nursit
 * Licence GPL
 *
 */


if (!defined('_ECRIRE_INC_VERSION')) return;


function dolibarr_declarer_tables_objets_sql($tables_principales){

	$tables_principales['spip_auteurs']['field']['dolibarr_socid'] = "varchar(35) NOT NULL DEFAULT ''";

	return $tables_principales;
}


/**
 * Installation/maj base
 *
 * @param string $nom_meta_base_version
 * @param string $version_cible
 */
function dolibarr_upgrade($nom_meta_base_version,$version_cible){
	$maj = array();
	// creation initiale

	$maj['create'] = array(
		array('maj_tables',array('spip_auteurs'))
	);

	$maj['0.1.1'] = array(
		array('maj_tables',array('spip_auteurs'))
	);

	// lancer la maj
	include_spip('base/upgrade');
	maj_plugin($nom_meta_base_version, $version_cible, $maj);
}

/**
 * Desinstallation/suppression des tables clusters
 *
 * @param string $nom_meta_base_version
 */
function dolibarr_vider_tables($nom_meta_base_version) {

	effacer_meta($nom_meta_base_version);
}

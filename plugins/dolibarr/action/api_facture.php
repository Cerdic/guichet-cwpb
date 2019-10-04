<?php
/*
 * Nursit.com
 * module de facturation
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012 - Distribue sous licence GNU/GPL
 *
 */

if (!defined('_ECRIRE_INC_VERSION')) return;

/**
 * API facture:
 * http://www.example.org/facture.api/970/691a1f8c02b9ba46f5320efe52bfdbea/FA1611-0234.pdf
 *
 * id_facture/hash securite/no comptable.pdf
 *
 */
function action_api_facture_dist(){

	if (!function_exists('lire_config')) {
		include_spip('inc/config');
	}

	// recuperer le token et le verifier
	$arg = _request('arg');
	$arg = explode('/', $arg);
	list($id_facture, $hash, $pdf_file) = $arg;
	
	if (!$id_facture
	  or !$hash
	  or !$pdf_file) {
		dolibarr_afficher_erreur_document(403);
		exit;
	}

	$facture = sql_fetsel('*', 'spip_factures', 'id_facture='.intval($id_facture));
	if (!$facture
	  or $hash!==md5($facture['details'])
	  or $pdf_file!==$facture['no_comptable'].'.pdf') {
		dolibarr_afficher_erreur_document(403);
		exit;
	}

	$pdf_file = _DIR_IMG . 'factures/' . $pdf_file;
	if (file_exists($pdf_file)) {

		// delivrer le PDF
		$embed = true;

		// toujours envoyer un content type
		header("Content-Type: application/pdf");
		// pour les images ne pas passer en attachment
		// sinon, lorsqu'on pointe directement sur leur adresse,
		// le navigateur les downloade au lieu de les afficher
		if (!$embed) {
			$f = basename($pdf_file);
			header("Content-Disposition: attachment; filename=\"$f\";");
			header("Content-Transfer-Encoding: binary");

			// fix for IE catching or PHP bug issue
			header("Pragma: public");
			header("Expires: 0"); // set expiration time
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		} else {
			header("Expires: 3600"); // set expiration time
		}

		if ($size = filesize($pdf_file)) {
			header("Content-Length: ". $size);
		}

		readfile($pdf_file);

	}
	else {
		dolibarr_afficher_erreur_document(404);
	}

}



/**
 * Affiche une page indiquant un document introuvable ou interdit
 *
 * @param string $status
 *     Numero d'erreur (403 ou 404)
 * @return void
**/
function dolibarr_afficher_erreur_document($status = 404) {

	switch ($status)
	{
		case 304:
			include_spip("inc/headers");
			// not modified : sortir de suite !
			http_status(304);
			exit;

		case 403:
			include_spip('inc/minipres');
			echo minipres("","","",true);
			break;

		case 404:
			http_status(404);
			include_spip('inc/minipres');
			echo minipres(_T('erreur') . ' 404', _T('medias:info_document_indisponible'), "", true);
			break;
	}
}

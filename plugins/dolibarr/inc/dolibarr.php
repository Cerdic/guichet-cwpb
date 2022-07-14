<?php
/**
 * Plugin dolibarr
 * (c) 2019 Cedric pour Nursit
 * Licence GPL
 *
 */


if (!defined('_ECRIRE_INC_VERSION')) return;

if (!defined('_DOLIBARR_DIR')) {
	die('Undefined _DOLIBARR_DIR');
}
if (!defined('_DOLIBARR_USER_DOLI')) {
	die('Undefined _DOLIBARR_USER_DOLI');
}
if (!defined('_DOLIBARR_ID_BANK_PAIEMENT')) {
	die('Undefined _DOLIBARR_ID_BANK_PAIEMENT');
}
if (!defined('_DOLIBARR_ID_BANK_PAIEMENT_STRIPE')) {
	die('Undefined _DOLIBARR_ID_BANK_PAIEMENT_STRIPE');
}

defined('_DOLIBARR_TYPE_PAIEMENT_CB') || define('_DOLIBARR_TYPE_PAIEMENT_CB', 6);
defined('_DOLIBARR_TYPE_PAIEMENT_CHEQUE') || define('_DOLIBARR_TYPE_PAIEMENT_CHEQUE', 7);
defined('_DOLIBARR_TYPE_PAIEMENT_VIREMENT') || define('_DOLIBARR_TYPE_PAIEMENT_VIREMENT', 2);


// Global variables
$version = '1.7';
$error = 0;

function dolibarr_connect() {
	static $connexion;
	global $db, $mysoc, $langs, $conf, $user, $hookmanager;
	if (is_null($connexion)) {
		// L'utilisateur dolibarr utilisé
		$utilisateur_dolibarr = _DOLIBARR_USER_DOLI;
		// Include Dolibarr environment
		// sanitizer les globales pour ne pas que dolibar nous emmene dans une route improbable
		// notamment avec un CONTENT_TYPE application/json sur un webhook stripe
		$backup = [];
		foreach(array('_COOKIE','_GET','_POST','_REQUEST','_SERVER','REQUEST_METHOD','methode',) as $k) {
			$backup[$k] = $GLOBALS[$k];
		}
		$GLOBALS['_COOKIE'] = [];
		$GLOBALS['_GET'] = [];
		$GLOBALS['_POST'] = [];
		$GLOBALS['_REQUEST'] = [];
		$GLOBALS['REQUEST_METHOD'] = 'GET';
		$GLOBALS['methode'] = 'GET';
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['QUERY_STRING'] = '';
		$_SERVER['REDIRECT_QUERY_STRING'] = '';
		unset($_SERVER['CONTENT_TYPE']);
		foreach(array(
			'HTTP_GET_VARS'=>'_GET',
			'HTTP_POST_VARS'=>'_POST',
			'HTTP_COOKIE_VARS'=>'_COOKIE',
			) as $k1=>$k2){
			$GLOBALS[$k1] = $GLOBALS[$k2];
		}
		require_once(_DOLIBARR_DIR . "master.inc.php");
		// restore globals
		foreach ($backup as $k=>$v) {
			$GLOBALS[$k] = $v;
		}
		foreach(array(
			'HTTP_GET_VARS'=>'_GET',
			'HTTP_POST_VARS'=>'_POST',
			'HTTP_COOKIE_VARS'=>'_COOKIE',
			) as $k1=>$k2){
			$GLOBALS[$k1] = $GLOBALS[$k2];
		}
		// After this $db, $mysoc, $langs and $conf->entity are defined. Opened handler to database will be closed at end of file.
		//spip_log('init : conf->entity='.var_export($conf->entity, true),'dolibarr');
		if ($db->lastqueryerror) {
			spip_log($db->lasterrno.' : '.$db->lasterror."\n".$db->lastqueryerror, 'dolibarr' . _LOG_ERREUR);
		}

		//$langs->setDefaultLang('fr_FR'); 	// To change default language of $langs
		setlocale(LC_ALL, 'fr_FR');

		$langs->load("main"); // To load language file for default language
		@set_time_limit(0);

		// Load user and its permissions
		$user->entity = $conf->entity;
		//spip_log(var_export($user, true),'dolibarr');
		$result = $user->fetch('', $utilisateur_dolibarr); // Load user for login 'admin'. Comment line to run as anonymous user.
		//spip_log('user->fetch : conf->entity='.var_export($conf->entity, true),'dolibarr');
		if ($db->lastqueryerror) {
			spip_log($db->lasterrno.' : '.$db->lasterror."\n".$db->lastqueryerror, 'dolibarr' . _LOG_ERREUR);
		}

		if (!$result > 0) {
			dol_print_error('', $user->error);
			exit;
		}
		$user->getrights();
		//spip_log('getrights : conf->entity='.var_export($conf->entity, true),'dolibarr');
		if ($db->lastqueryerror) {
			spip_log($db->lasterrno.' : '.$db->lasterror."\n".$db->lastqueryerror, 'dolibarr' . _LOG_ERREUR);
		}

		$connexion = array(
			'db' => &$db,
			'mysoc' => &$mysoc,
			'langs' => &$langs,
			'conf' => &$conf,
			'user' => &$user,
		);
	}

	return $connexion;
}



/**
 * @param $societe object
 * @param $soc_infos array
 *       - $nom
 *       - $prenom
 *       - $societe
 *       - $adresse
 *       - $complement_adresse
 *       - $boite_postale
 *       - $code_postal
 *       - $ville
 *       - $pays
 *       - $tel_fixe
 *       - $tel_mobile
 *       - $site
 *       - $email
 * @return bool
 */
function dolibarr_renseigne_societe(&$societe, $soc_infos) {
	$modif = false;



	$pays = array (
		'FR'=> 1,
		'BE'=>2,
		'CN'=>9,
		'US'=>11,
		'LU'=>140,
		'DE'=>5,
		'CA'=>14);


	$infos = array(
		'name' => trim(($soc_infos['societe'] ? $soc_infos['societe'] . " - " : '') . $soc_infos['nom'] . " " . $soc_infos['prenom']),
		'email' => $soc_infos['email'],
		'address' => trim($soc_infos['adresse'] . "\n" . $soc_infos['complement_adresse'] . "\n" . $soc_infos['boite_postale']),
		'zip' => $soc_infos['code_postal'],
		'town' => $soc_infos['ville'],
		'country_id' => $pays[$soc_infos['pays']],
		'phone' => $soc_infos['tel_fixe'],
		'url' => $soc_infos['site'],
	);

	foreach ($infos as $k=>$v) {
		if($societe->$k !== $infos[$k]) {
			$societe->$k = $infos[$k];
			$modif = true;
		}
	}

	return $modif;
}

/**
 * Inserer un nouveau client
 * @param $soc array
 * @return int|bool
 */
function dolibarr_societe_inserer($soc) {
	$connexion = dolibarr_connect();
	$db = &$connexion['db'];
	$user = &$connexion['user'];

	require_once(DOL_DOCUMENT_ROOT . "/compta/facture/class/facture.class.php");

	// Start of transaction
	$societe = new Societe ($db);
	dolibarr_renseigne_societe($societe, $soc);

	$monsocid = $societe->create($user);
	if ($monsocid > 0) {
		$societe->set_as_client();

		//print "   Société N°" . $monsocid . " créée\n";
		return $monsocid;
	}

	spip_log('doli_societe_inserer : erreur ' . $societe->error . "\n", 'dolibarr' . _LOG_ERREUR);
	if ($db->lastqueryerror) {
		spip_log($db->lasterrno.' : '.$db->lasterror."\n".$db->lastqueryerror, 'dolibarr' . _LOG_ERREUR);
	}

	return false;
}

/**
 * Mettre a jour un client existant
 * @param $socid int
 * @param $soc array
 * @return int|bool
 */
function dolibarr_societe_modifier($socid, $soc) {
	$connexion = dolibarr_connect();
	$db = &$connexion['db'];
	$user = &$connexion['user'];

	if (!$socid) return false;

	require_once(DOL_DOCUMENT_ROOT . "/compta/facture/class/facture.class.php");
	$societe = new Societe ($db);
	$societe->fetch($socid);
	if (dolibarr_renseigne_societe($societe, $soc)) {
		$societe->update($socid, $user);
		if ($societe->error) {
			spip_log('doli_societe_modifier : erreur ' . $societe->error . "\n", 'dolibarr' . _LOG_ERREUR);
			if ($db->lastqueryerror) {
				spip_log($db->lasterrno.' : '.$db->lasterror."\n".$db->lastqueryerror, 'dolibarr' . _LOG_ERREUR);
			}
		}
	}

	return $socid;
}

/**
 * @param $socid int
 *      le Numéro de client pour la facture
 * @param $lignes array
 *      le tableau contenant les lignes
 *                          'id_produit' => 2
 *                          'quantite' =>2,
 *                          'prix_unitaire'=>100,
 *                          'taux_tva' => 20,
 *                          'total_ht' => 200,
 *                          'total_tva'=>40,
 *                          'total_ttc' => 240,
 *                          'libelle' => "Hello world"
 *
 * @return bool|array
 */

function dolibarr_facture_inserer($socid, $lignes) {
	$connexion = dolibarr_connect();
	$db = &$connexion['db'];
	$user = &$connexion['user'];

	require_once(DOL_DOCUMENT_ROOT . "/compta/facture/class/facture.class.php");

	// Start of transaction
	$db->begin();

	$error = 0;

	// Create invoice object
	$facture = new Facture($db);
	$societe = new Societe($db);
	$societe->fetch($socid);
	$facture->socid = $socid; // Put id of third party (rowid in llx_societe table)

	$facture->date = mktime();
	$facture->cond_reglement_id = 1;

	foreach ($lignes as $ligne) {
		//$product = new Product ($db);
		//$product->fetch($ligne['id_produit']);

		$line1 = new FactureLigne($db);
		$line1->qty = $ligne['quantite'];
		$line1->subprice = price2num($ligne['prix_unitaire']);
		$line1->tva_tx = price2num($ligne['taux_tva']);

		$line1->total_ht = price2num($ligne['total_ht']);
		$line1->total_tva = price2num($ligne['total_tva']);
		$line1->total_ttc = price2num($ligne['total_ttc']);
		$line1->desc = $ligne['libelle'];
		$line1->fk_product = $ligne['id_produit'];

		if (!empty($ligne['date_debut'])) {
			$line1->date_start = $ligne['date_debut'];
		}
		if (!empty($ligne['date_fin'])) {
			$line1->date_end = $ligne['date_fin'];
		}

		$facture->lines[] = $line1;
	}

	// Create invoice
	// passer en lang en car sinon l'update du prix global de la facture plante a cause d'un cast float
	setlocale( LC_NUMERIC, 'en_US.UTF-8');
	$idobject = $facture->create($user);
	if ($idobject > 0) {
		// Change status to validated
		$result = $facture->validate($user);
		if ($result > 0) {
			$db->commit();

			setlocale( LC_NUMERIC, 'fr_FR');
			return array(
				'reference' => $facture->ref,
				'id' => $idobject,
			);

		}
	}

	setlocale( LC_NUMERIC, 'fr_FR');
	spip_log('doli_facture_inserer : erreur ' . $facture->error, 'dolibarr' . _LOG_ERREUR);
	if ($db->lastqueryerror) {
		spip_log($db->lasterrno.' : '.$db->lasterror."\n".$db->lastqueryerror, 'dolibarr' . _LOG_ERREUR);
	}
	$db->rollback();

	return false;
}

/**
 * Generer le PDF de la facture
 * @param $factid int
 * @param $factref string
 * @return bool|string
 */
function dolibarr_facture_pdf($factid, $factref = '') {
	$connexion = dolibarr_connect();
	$db = &$connexion['db'];
	$langs = &$connexion['langs'];
	$conf = &$connexion['conf'];

	require_once(DOL_DOCUMENT_ROOT . "/compta/facture/class/facture.class.php");
	require_once(DOL_DOCUMENT_ROOT . "/core/modules/facture/modules_facture.php");
	$facture = new Facture($db);
	$facture->fetch($factid, $factref);
	if ($facture and $facture->ref) {
		$hidedetails = (GETPOST('hidedetails', 'int') ? GETPOST('hidedetails', 'int') : (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0));
		$hidedesc = (GETPOST('hidedesc', 'int') ? GETPOST('hidedesc', 'int') : (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0));
		$hideref = (GETPOST('hideref', 'int') ? GETPOST('hideref', 'int') : (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0));
		$facture->generateDocument($facture->modelpdf, $langs, $hidedetails, $hidedesc, $hideref);
		$file = $conf->facture->dir_output . '/' . $facture->ref . '/' . $facture->ref . '.pdf';
		if ($facture->error) {
			spip_log('doli_facture_pdf : erreur ' . $facture->error, 'dolibarr' . _LOG_ERREUR);
			if ($db->lastqueryerror) {
				spip_log($db->lasterrno.' : '.$db->lasterror."\n".$db->lastqueryerror, 'dolibarr' . _LOG_ERREUR);
			}
		}
		return $file;
	}

	return false;
}

/**
 * @param $factid int
 *   numero de facture
 * @param $data
 *   date_paiement string
 *   montant int (montant en centimes?)
 *   type_paiement int
 *          2    Virement
 *          6    Carte Bancaire
 *          7    Chèque
 *   id_bank int
 *          1 (compte bancaire ?)
 *   libelle string
 *
 * @return bool
 */
function dolibarr_facture_payer($factid, $data) {
	$connexion = dolibarr_connect();
	$db = &$connexion['db'];
	$user = &$connexion['user'];

	require_once(DOL_DOCUMENT_ROOT . "/compta/facture/class/facture.class.php");


	if (intval(round($data['montant']*100)) !== 0) {
		require_once(DOL_DOCUMENT_ROOT . "/compta/paiement/class/paiement.class.php");
		// Start of transaction
		$db->begin();

		$paiement = new paiement($db);
		$paiement->datepaye = $data['date_paiement'];
		$paiement->amounts = array($factid => $data['montant']);
		$paiement->paiementid = $data['type_paiement'];
		$paiement->num_paiement = $data['libelle'];    // Numero du CHQ, VIR, etc...

		// passer en lang en car sinon l'update du prix global de la facture plante a cause d'un cast float
		setlocale( LC_NUMERIC, 'en_US.UTF-8');
		if ($id_paiement = $paiement->create($user)>0) {
		  $id_bank_line = $paiement->addPaymentToBank($user, "payment", "", $data['id_bank'], "", "");
		  if ($id_bank_line) {
			  $db->commit();
			  // et marquee payee donc (on ne verifie pas le monant, on fait pas de paiements par tranches)
				$facture = new Facture($db);
				$facture->fetch($factid);
				$facture->set_paid($user);
			  setlocale( LC_NUMERIC, 'fr_FR');
			  return $id_paiement;
		  }
		}

		setlocale( LC_NUMERIC, 'fr_FR');
		spip_log('doli_facture_payer : erreur ' . $paiement->error, 'dolibarr' . _LOG_ERREUR);
		if ($db->lastqueryerror) {
			spip_log($db->lasterrno.' : '.$db->lasterror."\n".$db->lastqueryerror, 'dolibarr' . _LOG_ERREUR);
		}
		$db->rollback();

	}
	else {
		// rien a payer, on marque juste la facture payee sans ligne de reglement
		$facture = new Facture($db);
		$facture->fetch($factid);
		$facture->set_paid($user);
		return -1;
	}

	return false;
}


/**
 * Recuperer une facture d'apres son id ou sa reference
 * @param $factid
 * @param string $factref
 * @return Facture
 */
function dolibarr_recuperer_facture($factid, $factref = '') {
	$connexion = dolibarr_connect();
	$db = &$connexion['db'];
	$langs = &$connexion['langs'];
	$conf = &$connexion['conf'];

	require_once(DOL_DOCUMENT_ROOT . "/compta/facture/class/facture.class.php");
	require_once(DOL_DOCUMENT_ROOT . "/core/modules/facture/modules_facture.php");
	$facture = new Facture($db);
	$facture->fetch($factid, $factref);

	return $facture;
}

function doli_normaliser_montant_decimal($montant) {
	$montant = str_replace(',', '.', $montant);
	$montant = intval(round($montant * 100)) / 100;
	$montant = str_replace(',', '.', $montant);
	return $montant;
}


function dolibarr_importer_facture_en_base_spip($factid, $factref = '') {
	$connexion = dolibarr_connect();
	$db = &$connexion['db'];
	$langs = &$connexion['langs'];
	$conf = &$connexion['conf'];
	$user = &$connexion['user'];

	require_once(DOL_DOCUMENT_ROOT . "/compta/facture/class/facture.class.php");
	require_once(DOL_DOCUMENT_ROOT . "/core/modules/facture/modules_facture.php");

	$facture = new Facture($db);
	$facture->fetch($factid, $factref);
	if (!$facture) {
		spip_log("inserer_facture_importer_en_base_spip : Impossible de retrouver la facture id=$factid ref=$factref dans dolibarr", "doli"._LOG_ERREUR);
		return false;
	}

	$reference = $facture->ref;
	$fact_ttc = doli_normaliser_montant_decimal($facture->total_ttc);
	$fact_ht = doli_normaliser_montant_decimal($facture->total_ht);
	$fact_tva = doli_normaliser_montant_decimal($facture->total_tva);

	// Si la facture existe dans spip_factures, on verifie que les montant sont OK et on retourne
	if ($facture_spip = sql_fetsel('*','spip_factures', 'no_comptable='.sql_quote($reference, '', 'text'))){
		$id_facture = $facture_spip['id_facture'];
		if (
			intval(round($facture_spip['montant_ht']*100)) !==intval(round($fact_ht*100))
			or intval(round($facture_spip['montant']*100)) !==intval(round($fact_ttc*100))

		){
			spip_log("inserer_facture_importer_en_base_spip : $reference dans dolibarr et id_facture=$id_facture ont des montants differents", "doli"._LOG_ERREUR);
			return false;
		}

		if (!preg_match(",<!--(.*)-->$,ms", $facture_spip['client'], $match)
		  or !$infos_client = json_decode($match[1], true)) {
			$infos_client = [];
		}

		return [$id_facture, $infos_client];
	}

	// Sinon preparer les infos pour creer la facture dans spip_factures
	$socid = $facture->socid;
	if (!$socid) {
		spip_log("inserer_facture_importer_en_base_spip : pas de socid pour facture $reference dans dolibarr", "doli"._LOG_ERREUR);
		return false;
	}
	$id_auteur = sql_getfetsel('id_auteur', 'spip_auteurs', 'dolibarr_socid='.sql_quote($socid));
	$id_auteur = intval($id_auteur);

	if (!$facture->date_validation) {
		spip_log("inserer_facture_importer_en_base_spip : facture $reference non validee dans dolibarr", "doli"._LOG_ERREUR);
		return false;
	}
	$fact_date = date('Y-m-d H:i:s',$facture->date_validation);

	$societe = new Societe($db);
	$societe->fetch($socid);
	if (!$societe) {
		spip_log("inserer_facture_importer_en_base_spip : impossible de retrouver socid=$socid pour facture $reference dans dolibarr", "doli"._LOG_ERREUR);
		return false;
	}

	$client = $societe->name . "<br />\n"
	  . $societe->address . "<br />\n"
	  . $societe->zip . ' ' . $societe->town  . "<br />\n"
	  . $societe->country_code;

	$infos_client = [
		'name' => $societe->name,
		'address' => $societe->address,
		'zip' => $societe->zip,
		'city' => $societe->town,
		'state' => $societe->country_code,
		'email' => $societe->email,
	];

	$client_json = json_encode($infos_client);
	$client .= "\n<!--$client_json-->";

	$details = "";
	foreach ($facture->lines as $line){
		$s = "<td class='produit'>" . $line->desc . "</td>\n";
		$s .= "<td class='prix_ht'>" . round($line->total_ht,2) . "</td>\n";
		$s .= "<td class='tva'>" . round($line->total_tva, 2) . "</td>\n";
		$s .= "<td class='prix_ttc'>" . round($line->total_ttc, 2) . "</td>\n";
		$s = "<tr>\n$s\n</tr>";
		$details .= "$s\n";
	}

	$details = "<table class='spip panier'>
	<thead>
		<th class='produit'>Produit</th>
		<th class='prix_ht'>Prix HT</th>
		<th class='tva'>TVA</th>
		<th class='prix_ttc'>Prix TTC</th>
	</thead>
	<tbody>
	$details
	</tbody>
	<tfoot>
		<tr>
			<td class='produit' colspan='3'>Total HT</td>
			<td class='prix'>" . affiche_monnaie($fact_ht) . "</td>
		</tr>
		<tr>
			<td class='produit' colspan='3'>TVA</td>
			<td class='prix'>" . affiche_monnaie($fact_tva) . "</td>
		</tr>
		<tr>
			<td class='produit' colspan='3'>TTC</td>
			<td class='prix'>" . affiche_monnaie($fact_ttc) . "</td>
		</tr>
	</tfoot>
</table>
";


	$set = array(
		'id_auteur' => $id_auteur,
		'no_comptable' => $reference,
		'montant_ht' => $fact_ht,
		'montant' => $fact_ttc,
		'date' => $fact_date,
		'client' => $client,
		'details' => $details,
		'parrain' => 'dolibarr',
		'tracking_id' => $facture->id,
	);

	$id_facture = sql_insertq('spip_factures',$set);

	return [$id_facture, $infos_client];
}

/**
 *
 */
/*
$data2 = array(
    'date_paiement' => date("Y-m-d H:i:s"),
    'montant' => 500,
    'type_paiement' => 2,
    'id_bank' => 1,
    'num_paiement' => "cheque numero 5"
);
$id_paiement = createPaiement($langs, $user, $db, $data2);
*/

<?php
/**
 * Plugin toolbox
 * Boite a outils Nursit
 * (c) 2011 Cedric pour Nursit.net
 * Licence GPL
 *
 */


if (!defined('_ECRIRE_INC_VERSION')) return;

if (!defined('_DIR_DOLI')) {
	die('Undefined _DIR_DOLI');
}

// Global variables
$version = '1.7';
$error = 0;

function doli_connect() {
	static $connexion;
	global $db, $mysoc, $langs, $conf, $user, $hookmanager;
	if (is_null($connexion)) {
		// L'utilisateur dolibarr utilisé
		$utilisateur_dolibarr = "webmaster@nursit.net";
		// Include Dolibarr environment
		require_once(_DIR_DOLI . "master.inc.php");
		// After this $db, $mysoc, $langs and $conf->entity are defined. Opened handler to database will be closed at end of file.
		//spip_log('init : conf->entity='.var_export($conf->entity, true),'doli');
		if ($db->lastqueryerror) {
			spip_log($db->lasterrno.' : '.$db->lasterror."\n".$db->lastqueryerror, 'doli' . _LOG_ERREUR);
		}

		//$langs->setDefaultLang('fr_FR'); 	// To change default language of $langs
		setlocale(LC_ALL, 'fr_FR');

		$langs->load("main"); // To load language file for default language
		@set_time_limit(0);

		// Load user and its permissions
		$user->entity = $conf->entity;
		//spip_log(var_export($user, true),'doli');
		$result = $user->fetch('', $utilisateur_dolibarr); // Load user for login 'admin'. Comment line to run as anonymous user.
		//spip_log('user->fetch : conf->entity='.var_export($conf->entity, true),'doli');
		if ($db->lastqueryerror) {
			spip_log($db->lasterrno.' : '.$db->lasterror."\n".$db->lastqueryerror, 'doli' . _LOG_ERREUR);
		}

		if (!$result > 0) {
			dol_print_error('', $user->error);
			exit;
		}
		$user->getrights();
		//spip_log('getrights : conf->entity='.var_export($conf->entity, true),'doli');
		if ($db->lastqueryerror) {
			spip_log($db->lasterrno.' : '.$db->lasterror."\n".$db->lastqueryerror, 'doli' . _LOG_ERREUR);
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
function doli_renseigne_societe(&$societe, $soc_infos) {
	$modif = false;
	$infos = array(
		'name' => trim(($soc_infos['societe'] ? $soc_infos['societe'] . " - " : '') . $soc_infos['nom'] . " " . $soc_infos['prenom']),
		'email' => $soc_infos['email'],
		'address' => trim($soc_infos['adresse'] . "\n" . $soc_infos['complement_adresse'] . "\n" . $soc_infos['boite_postale']),
		'zip' => $soc_infos['code_postal'],
		'town' => $soc_infos['ville'],
		'country_code' => $soc_infos['pays'],
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
function doli_societe_inserer($soc) {
	$connexion = doli_connect();
	$db = &$connexion['db'];
	$user = &$connexion['user'];

	require_once(DOL_DOCUMENT_ROOT . "/compta/facture/class/facture.class.php");

	// Start of transaction
	$societe = new Societe ($db);
	doli_renseigne_societe($societe, $soc);

	$monsocid = $societe->create($user);
	if ($monsocid > 0) {
		$societe->set_as_client();

		//print "   Société N°" . $monsocid . " créée\n";
		return $monsocid;
	}

	spip_log('doli_societe_inserer : erreur ' . $societe->error . "\n", 'doli' . _LOG_ERREUR);
	if ($db->lastqueryerror) {
		spip_log($db->lasterrno.' : '.$db->lasterror."\n".$db->lastqueryerror, 'doli' . _LOG_ERREUR);
	}

	return false;
}

/**
 * Mettre a jour un client existant
 * @param $socid int
 * @param $soc array
 * @return int|bool
 */
function doli_societe_modifier($socid, $soc) {
	$connexion = doli_connect();
	$db = &$connexion['db'];
	$user = &$connexion['user'];

	if (!$socid) return false;

	require_once(DOL_DOCUMENT_ROOT . "/compta/facture/class/facture.class.php");
	$societe = new Societe ($db);
	$societe->fetch($socid);
	if (doli_renseigne_societe($societe, $soc)) {
		$societe->update($socid, $user);
		if ($societe->error) {
			spip_log('doli_societe_modifier : erreur ' . $societe->error . "\n", 'doli' . _LOG_ERREUR);
			if ($db->lastqueryerror) {
				spip_log($db->lasterrno.' : '.$db->lasterror."\n".$db->lastqueryerror, 'doli' . _LOG_ERREUR);
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
 * @return int
 */

function doli_facture_inserer($socid, $lignes) {
	$connexion = doli_connect();
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
		$line1->subprice = $ligne['prix_unitaire'];
		$line1->tva_tx = $ligne['taux_tva'];

		$line1->total_ht = $ligne['total_ht'];
		$line1->total_tva = $ligne['total_tva'];
		$line1->total_ttc = $ligne['total_ttc'];
		$line1->desc = $ligne['libelle'];
		$line1->fk_product = $ligne['id_produit'];

		$facture->lines[] = $line1;
	}

	// Create invoice
	$idobject = $facture->create($user);
	if ($idobject > 0) {
		// Change status to validated
		$result = $facture->validate($user);
		if ($result > 0) {
			$db->commit();

			return array(
				'reference' => $facture->ref,
				'id' => $idobject,
			);

		}
	}

	spip_log('doli_facture_inserer : erreur ' . $facture->error, 'doli' . _LOG_ERREUR);
	if ($db->lastqueryerror) {
		spip_log($db->lasterrno.' : '.$db->lasterror."\n".$db->lastqueryerror, 'doli' . _LOG_ERREUR);
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
function doli_facture_pdf($factid, $factref = '') {
	$connexion = doli_connect();
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
			spip_log('doli_facture_pdf : erreur ' . $facture->error, 'doli' . _LOG_ERREUR);
			if ($db->lastqueryerror) {
				spip_log($db->lasterrno.' : '.$db->lasterror."\n".$db->lastqueryerror, 'doli' . _LOG_ERREUR);
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
function doli_facture_payer($factid, $data) {
	$connexion = doli_connect();
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

		if ($id_paiement = $paiement->create($user)>0) {
		  $id_bank_line = $paiement->addPaymentToBank($user, "payment", "", $data['id_bank'], "", "");
		  if ($id_bank_line) {
			  $db->commit();
			  // et marquee payee donc (on ne verifie pas le monant, on fait pas de paiements par tranches)
				$facture = new Facture($db);
				$facture->fetch($factid);
				$facture->set_paid($user);
			  return $id_paiement;
		  }
		}

		spip_log('doli_facture_payer : erreur ' . $paiement->error, 'doli' . _LOG_ERREUR);
		if ($db->lastqueryerror) {
			spip_log($db->lasterrno.' : '.$db->lasterror."\n".$db->lastqueryerror, 'doli' . _LOG_ERREUR);
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
function doli_recuperer_facture($factid, $factref = '') {
	$connexion = doli_connect();
	$db = &$connexion['db'];
	$langs = &$connexion['langs'];
	$conf = &$connexion['conf'];

	require_once(DOL_DOCUMENT_ROOT . "/compta/facture/class/facture.class.php");
	require_once(DOL_DOCUMENT_ROOT . "/core/modules/facture/modules_facture.php");
	$facture = new Facture($db);
	$facture->fetch($factid, $factref);

	return $facture;
}


function doli_importer_facture_en_base_spip($factid, $factref = '') {
	$connexion = doli_connect();
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
	$fact_ttc = $facture->total_ttc;
	$fact_ht = $facture->total_ht;
	$fact_tva = $facture->total_tva;

	// Si la facture existe dans spip_factures, on verifie que les montant sont OK et on retourne
	if ($facture_spip = sql_fetsel('*','spip_factures', 'no_comptable='.sql_quote($reference, '', 'text'))){
		$id_facture = $facture_spip['id_facture'];
		if (
			intval(round(str_replace(',','.',$facture_spip['montant_ht'])*100))!==intval(round($fact_ht*100))
			or intval(round(str_replace(',','.',$facture_spip['montant'])*100))!==intval(round($fact_ttc*100))

		){
			spip_log("inserer_facture_importer_en_base_spip : $reference dans dolibarr et id_facture=$id_facture ont des montants differents", "doli"._LOG_ERREUR);
			return false;
		}

		return $id_facture;
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

	$client = $socid->name . "<br />\n"
	  . $socid->address . "<br />\n"
	  . $socid->zip . ' ' . $socid->town  . "<br />\n"
	  . $socid->country_code;

	$details = "";
	foreach ($facture->lines as $line){
		$s = "<td class='produit'>" . $line->desc . "</td>\n";
		$s .= "<td class='prix_ht'>" . $line->total_ht . "</td>\n";
		$s .= "<td class='tva'>" . $line->total_tva . "</td>\n";
		$s .= "<td class='prix_ttc'>" . $line->total_ttc . "</td>\n";
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
		'montant_ht' => round($fact_ht,2),
		'montant' => round($fact_ttc, 2),
		'date' => $fact_date,
		'client' => $client,
		'details' => $details,
		'parrain' => 'dolibarr',
	);
	$id_facture = sql_insertq('spip_factures',$set);

	return $id_facture;
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




// Tests
//
/*

$script_file = basename(__FILE__);
print "***** " . $script_file . " (" . $version . ") *****\n";

#Id client dolibarr
$socid = 114;
#ID produit Dolibarr
$id_produit = 2;

$lignes = array(
	array(
		'id_produit' => 2,
		'quantite' => 2,
		'prix_unitaire' => 100,
		'taux_tva' => 20,
		'total_ht' => 200,
		'total_tva' => 40,
		'total_ttc' => 240,
		'libelle' => "Hello world"
	)
);

$error = createDoliFacture($socid, $id_produit, $lignes);

$data = array(
	'nom' => "Mamet",
	'prenom' => "Benoit",
	'societe' => "Nursit",
	'adresse' => "37 rue d'antin",
	'complement_adresse' => "au bout de la rue",
	'boite_postale' => "",
	'code_postal' => 59000,
	'ville' => "Lille",
	'pays' => "FR",
	'tel' => "03.20.54.64.29",
	'site' => "http://nursit.com",
	'email' => "bmamet@nordnet.fr"
);


$error += createSociete($data);
*/


<?php
/**
 * Plugin dolibarr
 * (c) 2019 Cedric pour Nursit
 * Licence GPL
 *
 */


if (!defined('_ECRIRE_INC_VERSION')) return;
include_spip('inc/dolibarr');

/**
 * Creer/update le client/societe dans dolibarr
 * @param $id_auteur
 * @return bool|int
 */
function dolibarr_actualiser_societe($id_auteur) {
	$auteur = sql_fetsel('*', 'spip_auteurs', 'id_auteur='.intval($id_auteur));
	if (!$auteur) {
		spip_log("actualiser_societe_dolibar : $id_auteur non trouve", "dolibarr"._LOG_ERREUR);
		return false;
	}

	$client = array(
		'nom' => sinon($auteur['name'], $auteur['nom']),
		'prenom' => $auteur['prenom'],
		'societe' => $auteur['societe'],
		'adresse' => $auteur['adresse_1'],
		'complement_adresse' => $auteur['adresse_2'],
		'boite_postale' => $auteur['adresse_bp'],
		'code_postal' => $auteur['adresse_cp'],
		'ville' => $auteur['adresse_ville'],
		'pays' => $auteur['adresse_pays'],
		'tel_fixe' => $auteur['tel_fixe'],
		'tel_mobile' => $auteur['tel_mobile'],
		'site' => $auteur['url_site'],
		'email' => $auteur['email'],
	);
	if (!$socid = $auteur['dolibarr_socid']) {
		$socid = dolibarr_societe_inserer($client);
		if (!$socid) {
			spip_log("actualiser_societe_dolibar : insertion societe impossible ". var_export($client,true), "dolibarr"._LOG_ERREUR);
			return false;
		}
		// mettre a jour le socid en base SPIP
		sql_updateq('spip_auteurs', array('dolibarr_socid'=>$socid), 'id_auteur='.intval($id_auteur));
		spip_log("actualiser_societe_dolibar : insertion OK auteur #".$auteur['id_auteur']." -> socid=".$socid,'dolibarr');
	}
	else {
		dolibarr_societe_modifier($socid, $client);
		spip_log("actualiser_societe_dolibar : auteur #".$auteur['id_auteur']." -> socid=".$socid,'dolibarr');
	}

	return $socid;
}

/**
 * Generer la facture dans dolibarr
 * @param $id_facture int
 * @param $facture array
 * @return mixed
 */
function dolibarr_generer_facture($id_facture, $facture) {

	$socid = dolibarr_actualiser_societe($facture['id_auteur']);
	if (!$socid) {
		spip_log("generer_facture_dolibarr : Facture $id_facture sans auteur", "dolibarr"._LOG_ERREUR);
		return $facture;
	}

	$lignes = array();

	$transaction = sql_fetsel('*','spip_transactions','id_facture='.intval($id_facture));
	//spip_log('transaction : '.var_export($transaction, true),'dolibarr');
	$items = paniers_explique_cookie($transaction['contenu']);
	//spip_log('items : '.var_export($items, true),'dolibarr');
	foreach ($items as $item) {

		$taux_tva = 0;
		if (intval($item['net_price'] * 100)) {
			$taux_tva = round(($item['gross_price'] - $item['net_price']) / $item['net_price'] * 100,1);
		}

		// TODO : code generique
		$ligne = array(
			'id_produit' => 2, // produit hebergement par defaut
			'quantite' => $item['quantity'],
			'prix_unitaire' => round($item['net_price'] / $item['quantity'],2),
			'taux_tva' => $taux_tva,
			'total_ht' => $item['net_price'],
			'total_tva' => $item['gross_price'] - $item['net_price'],
			'total_ttc' => $item['gross_price'],
			'libelle' => '',
		);

		$texte = "";
		if ($item['id_syndic'] and $url = generer_info_entite($item['id_syndic'], 'site', 'url_site')) {
			$texte = $url."<br />\n";
		}
		$date_echeance = date_echeance_contextuelle($item['id_syndic'], $item['id'], $item['quantity']);
		$texte .= affiche_produit_clair($item['id'], $item['quantity'], $item['id_syndic'], $date_echeance);
		$ligne['libelle'] = $texte;

		// produits qui ne sont pas de l'hebergement
		$produit = array('forfmigration' => 6, 'forfdns' => 6, 'ndd' => 9);
		if (isset($produit[$item['id']])) {
			$ligne['id_produit'] = $produit[$item['id']];
		}

		$lignes[] = $ligne;

	}

	// verifier les totaux
	$total_ht = $total_ttc = 0;
	$k = 0;
	foreach($lignes as $k=>$ligne) {
		$total_ht += $ligne['total_ht'];
		$total_ttc += $ligne['total_ttc'];
	}
	// on ajuste le montant de la derniere ligne si l'arrondi ne tombe pas juste
	$ecart_ht = round($facture['montant_ht'] - $total_ht, 2);
	if ($ecart_ht >= 0.01) {
		$lignes[$k]['total_ht'] += $ecart_ht;
	}
	$ecart_ttc = round($facture['montant_ttc'] - $total_ttc, 2);
	if ($ecart_ttc >= 0.01) {
		$lignes[$k]['total_ttc'] += $ecart_ttc;
	}

	spip_log("doli_facture_inserer $socid ".var_export($lignes,true), "dolibarr");
	$res = dolibarr_facture_inserer($socid, $lignes);
	spip_log("res=".var_export($res,true), 'dolibarr');
	if (!$res or !$res['reference']) {
		spip_log("echec creation facture #$id_facture dans dolibarr ". var_export($lignes,true), "dolibarr"._LOG_ERREUR);
		return $facture;
	}

	$facture['no_comptable'] = $res['reference'];
	sql_updateq('spip_factures', array('no_comptable' => $facture['no_comptable']),'id_facture='.intval($id_facture));

	$factid = $res['id'];

	return $facture;
}
<?php
/**
 * Plugin dolibarr
 * (c) 2019 Cedric pour Nursit
 * Licence GPL
 *
 */


if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('inc/dolibarr');


function inserer_transaction_achat_produits_dolibarr($id_auteur, $id_produits, $produits_liste) {

	$montant_ht = 0;
	$montant_ttc = 0;
	$items = [];

	foreach ($id_produits as $id_produit) {
		if (!empty($produits_liste[$id_produit])) {
			$produit = $produits_liste[$id_produit];

			$desc = $produit['titre'];
			if (!empty($produit['description'])) {
				$desc .= "<br />\n" . $produit['description'];
			}

			$qte = 1;
			$item = [
				'id_produit' => $produit['id'] ?? $id_produit,
				'taux_tva' => $produit['tva_tx'] ?? null,
				'quantity' => $qte,
				'net_price' => ($qte === 1 ? $produit['prix_ht'] : $qte * $produit['prix_ht']),
				'gross_price' => ($qte === 1 ? $produit['prix_ttc'] : $qte * $produit['prix_ttc']),
				'description' => $desc,
			];

			$montant_ht += $item['net_price'];
			$montant_ttc += $item['gross_price'];
			$items[] = $item;
		}
	}

	$inserer_transaction = charger_fonction('inserer_transaction', 'bank');
	$options = array(
		'id_auteur' => $id_auteur,
		'montant_ht' => $montant_ht,
		'parrain' => 'achatdoli',
		'champs' => array(
			'contenu' => json_encode($items)
		),
	);

	$id_transaction = $inserer_transaction($montant_ttc, $options);

	return $id_transaction;
}

function dolibarr_lister_produits($refs) {
	$connexion = dolibarr_connect();
	$db = &$connexion['db'];
	$user = &$connexion['user'];

	require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

	$liste_produits = [];
	foreach ($refs as $ref) {
		$produit = new Product ($db);
		$produit->fetch('', $ref);

		if ($produit and $produit->status) {

			$p = [
				'id' => $produit->id,
				'titre' => $produit->label,
				'description' => $produit->description,
				'prix_ht' => $produit->price,
				'prix_ttc' => $produit->price_ttc,
				'tva_tx' => $produit->tva_tx,
			];
			$liste_produits[$p['id']] = $p;
		}

	}

	return $liste_produits;

}


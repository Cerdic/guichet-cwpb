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
 * Regler la facture dans dolibarr et generer le PDF
 * @param $id_transaction int
 * @return bool
 */
function dolibarr_regler_facture($id_transaction) {
	spip_log("regler_facture_dolibarr $id_transaction 1",'dolibarr' . _LOG_DEBUG);
	$transaction = sql_fetsel('*','spip_transactions','id_transaction='.intval($id_transaction));
	if(!$transaction or !$id_facture = $transaction['id_facture']) {
		return false;
	}
	spip_log("regler_facture_dolibarr $id_transaction 2",'dolibarr' . _LOG_DEBUG);

	$facture = sql_fetsel('*','spip_factures','id_facture='.intval($id_facture));
	if ($facture['no_comptable']) {
		spip_log("regler_facture_dolibarr $id_transaction 3",'dolibarr' . _LOG_DEBUG);
		$factref = $facture['no_comptable'];

		$facture_doli = dolibarr_recuperer_facture(null, $factref);
		if (!$facture_doli) {
			spip_log("regler_facture_dolibarr $id_transaction ECHEC dolibarr_recuperer_facture",'dolibarr' . _LOG_ERREUR);
		}
		elseif (!$factid = $facture_doli->id) {
			spip_log("regler_facture_dolibarr $id_transaction ECHEC dolibarr_recuperer_facture pas de factid",'dolibarr' . _LOG_ERREUR);
			spip_log($facture_doli,'dolibarr' . _LOG_ERREUR);
		}
		else {
			spip_log("regler_facture_dolibarr $id_transaction 4",'dolibarr' . _LOG_DEBUG);
			$fact_paye = $facture_doli->paye;
			if ($fact_paye) {
				spip_log("regler_facture_dolibarr $id_transaction Facture DOLI $factref/#$factid DEJA PAYEE",'dolibarr' . _LOG_DEBUG);
			}
			elseif ($transaction['reglee'] !== 'oui') {
				spip_log("regler_facture_dolibarr $id_transaction reglee!=oui",'dolibarr' . _LOG_DEBUG);
			}
			// marquer la facture comme payee dans dolibarr (sauf si montant nul, doli ne sait pas faire)
			else {
				spip_log("regler_facture_dolibarr $id_transaction 5",'dolibarr' . _LOG_DEBUG);
				$libelle = _T('bank:titre_transaction').' #'.$transaction['id_transaction']; //.' | '.$transaction['mode'].' '.$transaction['autorisation_id'];
				$paiement = array(
						'date_paiement' => $transaction['date_paiement'],
						'montant' => $facture['montant_regle'],
						'type_paiement' => _DOLIBARR_TYPE_PAIEMENT_CB,
						'id_bank' => _DOLIBARR_ID_BANK_PAIEMENT, /* Banque CA */
						'libelle' => trim($libelle),
				);

				if (strncmp($transaction['mode'],'cheque',6) == 0) {
					$paiement['type_paiement'] = _DOLIBARR_TYPE_PAIEMENT_CHEQUE;
				}
				if (strncmp($transaction['mode'],'virement',8) == 0) {
					$paiement['type_paiement'] = _DOLIBARR_TYPE_PAIEMENT_VIREMENT;
				}
				if (strncmp($transaction['mode'],'stripe',6) == 0) {
					$paiement['id_bank'] = _DOLIBARR_ID_BANK_PAIEMENT_STRIPE; /* id Compte Banque Stripe */
				}
				//spip_log("paiement $factid ".var_export($paiement,true),'dolibarr');
				$id_paiement = dolibarr_facture_payer($factid, $paiement);
				spip_log("paiement $factid : $id_paiement",'dolibarr');


				if ($facture['parrain'] == 'dolibarr' && defined('_DOLIBARR_EMAIL_NOTIF_REGLEMENT_FACTURE')) {
					$sujet = "Reglement CB Facture Dolibarr $factref";
					$url= generer_url_public('facture',"id_facture=$id_facture&hash=".md5($facture['details']),false,false);
					$url_fac_doli = false;
					if (defined('_DOLIBARR_PUBLIC_URL')) {
						$url_fac_doli = _DOLIBARR_PUBLIC_URL . "compta/facture/card.php?facid=$factid";
					}
					$texte =
						"<html>
<body>
<p><a href='$url'>$url</a></p>"
. ($url_fac_doli ? "<p><a href='$url_fac_doli'>$url_fac_doli</a></p>" : '')
."<div>" . $facture['client'] . "<br /></div>"
.$facture['details']."
</body>
</html>";

					$envoyer_mail = charger_fonction('envoyer_mail','inc');
					$envoyer_mail(_DOLIBARR_EMAIL_NOTIF_REGLEMENT_FACTURE, $sujet, $texte);
					//include_spip('inc/notifications');
					//notifications_envoyer_mails(, $texte, $sujet);
				}
			}

			dolibarr_pdfiser_facture($facture['no_comptable']);
			return true;
		}
	}
	return false;
}


/**
 * Regenerer le PDF dans Dolibarr
 * @param $factref string
 */
function dolibarr_pdfiser_facture($factref) {

	// Generer le PDF et le recopier dans SPIP
	if ($factref
		and $pdf_file = dolibarr_facture_pdf(0, $factref)
	  and file_exists($pdf_file)) {
		$dir_pdf = sous_repertoire(_DIR_IMG,"factures");
		if (!file_exists($dir_pdf.".htaccess"))
			ecrire_fichier($dir_pdf.".htaccess","deny from all\n");
		@copy($pdf_file, $dir_pdf . texte_script($factref) . '.pdf');
	}
	else {
		spip_log("fichier pdf dolibarr manquant ".$pdf_file,'dolibarr'._LOG_ERREUR);
	}

}
<?php

define('_DIR_PLUGINS_SUPPL', _DIR_RACINE . "squelettes/plugins/");
define('_COULEUR_EMAILS_HTML', '#005b7b');

function autoriser_transaction_facturer_dist($faire, $type, $id, $qui, $opt) {

	if ($id_transaction = intval($id)
	  and $transaction = sql_fetsel('*', 'spip_transactions', 'id_transaction='.intval($id))) {

		// si c'est un DON ou une ADHESION, pas de facture
		if (in_array($transaction['parrain'], ['don', 'adhesion'])) {
			return false;
		}

		// si c'est montant=0 pas de facture
		if (!intval($transaction['montant'] * 100)) {
			return false;
		}

	}

	return true;
}


foreach (['bank_dsp2_renseigner_facturation',
	         'bank_description_transaction',
	         'trig_bank_notifier_reglement'] as $pipe) {
	if (!isset($GLOBALS['spip_pipeline'][$pipe])) {
		$GLOBALS['spip_pipeline'][$pipe] = '';
	}
	$GLOBALS['spip_pipeline'][$pipe] .= '|guichet_' . $pipe;
}

/**
 * Renseigner les infos clients pour le paiement CB
 * @param $flux
 * @return mixed
 */
function guichet_bank_dsp2_renseigner_facturation($flux) {

	// si c'est une transaction associee a un don
	if ($id_transaction = $flux['args']['id_transaction']
	  AND in_array($flux['args']['parrain'], ['don', 'adhesion'])
	  AND $auteur = $flux['args']['auteur']){

		$auteur = explode(' ', $auteur);
		$flux['data']['email'] = array_pop($auteur);
		$flux['data']['nom'] = implode(' ', $auteur);
	}

	return $flux;
}

function guichet_bank_description_transaction($flux) {

	// si c'est une transaction associee a un don
	if ($id_transaction = $flux['args']['id_transaction']
	  AND $flux['args']['parrain'] == 'don'
	  AND $raison = $flux['args']['contenu']){
		$flux['data']['libelle'] = $raison;
	}

	if ($id_transaction = $flux['args']['id_transaction']
	  AND $flux['args']['parrain'] == 'adhesion'
	  AND $raison = $flux['args']['contenu']){
		$flux['data']['libelle'] = _T('guichet:libelle_adhesion');
	}

	return $flux;

}

function guichet_trig_bank_notifier_reglement($flux) {
	if ($id_transaction = $flux['args']['id_transaction']
	  AND in_array($flux['args']['row']['parrain'], ['don', 'adhesion'])
	  AND $auteur = $flux['args']['row']['auteur']){

		$what = $flux['args']['row']['parrain'];
		$auteur = explode(' ', $auteur);
		$email = array_pop($auteur);
		$texte = recuperer_fond('notifications/email_reglement_' . $what, array('id_transaction' => $id_transaction));

		include_spip('inc/config');
		$from = lire_config('bank/email_from_ticket_admin', 'administratif@coworking-pb.com');

		include_spip("inc/notifications");
		notifications_envoyer_mails($email, $texte, '', $from);

		if ($what === 'adhesion'
		  && defined('_GUICHET_EMAIL_NOTIF_REGLEMENT_ADHESION')) {
			lang_select('fr');
			$texte = recuperer_fond('notifications/email_reglement_' . $what."_admin", array('id_transaction' => $id_transaction));
			notifications_envoyer_mails(_GUICHET_EMAIL_NOTIF_REGLEMENT_ADHESION, $texte, '', $from);
			lang_select();
		}

		if ($what === 'don'
		  && strpos($flux['args']['row']['contenu'], '10 ans') !== false // notif pour la soiree des 10 ans
		  && defined('_GUICHET_EMAIL_NOTIF_REGLEMENT_10ANS')) {
			lang_select('fr');
			$texte = recuperer_fond('notifications/email_reglement_' . $what."_admin", array('id_transaction' => $id_transaction));
			notifications_envoyer_mails(_GUICHET_EMAIL_NOTIF_REGLEMENT_10ANS, $texte, '', $from);
			lang_select();
		}

	}

	return $flux;
}
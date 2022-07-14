<?php
/**
 * Plugin Clients
 * Gestion des comptes clients
 * (c) 2011 Cedric pour Nursit.net
 * Licence GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;


function formulaires_editer_profil_charger_dist($id_auteur){
	if (!$id_auteur
	  OR !$auteur = sql_fetsel('*','spip_auteurs','id_auteur='.intval($id_auteur)))
		return false;


	$valeurs = array('newsletter' => '');
	foreach(array('name',
	              'prenom',
	              'email',
	              'societe',
	              'adresse_1',
	              'adresse_2',
	              'adresse_bp',
	              'adresse_cp',
	              'adresse_ville',
	              'adresse_pays',
	              'tel_fixe',
	              'tel_mobile') as $champ)
		$valeurs[$champ] = $auteur[$champ];

	// abonne a la newsletter ?
	if ($auteur['email']){
		$subscriber = charger_fonction("subscriber","newsletter");
		$infos = $subscriber($auteur['email']);
		if ($infos
			AND $infos['status']=='on'
			AND in_array('clients',$infos['listes']))
			$valeurs['newsletter'] = 1;
	}

	return $valeurs;
}


function formulaires_editer_profil_verifier_dist($id_auteur){
	$erreurs = array();

	$oblis = array('name',
	              'prenom',
	              'email',
	              'adresse_1',
	              'adresse_cp',
	              'adresse_ville',
	              'adresse_pays');

	foreach($oblis as $obli)
		if (!strlen(trim(_request($obli)))) {
			$erreurs[$obli] = _T('editer_profil:erreur_' . $obli . '_obligatoire');
		}

	// Verifier l'email
	if (!isset($erreurs['email'])){
		$email = trim(_request('email'));
		if (!email_valide($email))
			$erreurs['email'] = _T('nursit:erreur_email_invalide');
		else {
			//...
		}
	}

	return $erreurs;
}

function formulaires_editer_profil_traiter_dist($id_auteur){
	$auteur = sql_fetsel('*','spip_auteurs','id_auteur='.intval($id_auteur));
	$email = trim(_request('email'));

	if ($auteur['email']==$auteur['login']
	  AND $email
	  AND $email!==$auteur['email']
	) {
		set_request('login', $email);
	}

	include_spip('inc/editer');
	// renseigner le nom de la table auteurs :
	$prenom = trim(_request('prenom'));
	$nom = trim(_request('name'));
	set_request('nom', $prenom . ' ' . $nom);
	$res = formulaires_editer_objet_traiter('auteur', $id_auteur);


	if (_request('newsletter')){
		$subscribe = charger_fonction("subscribe", "newsletter");
		$subscribe($email,array('nom'=>_request('nom'),'listes'=>array('clients'),'force'=>true));
	} else {
		$unsubscribe = charger_fonction("unsubscribe","newsletter");
		$unsubscribe($email,array('listes'=>array('clients')));
	}
	set_request('newsletter');


	if (isset($res['message_ok'])){
		$res['message_ok'] = _T('editer_profil:message_ok_profil_modifie');
		$res['editable'] = true;
	}
	return $res;
}
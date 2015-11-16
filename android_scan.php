<?php
session_start();
include_once('UTILS/log.php');
include_once('UTILS/gestion_erreur.php');
include_once('MODELE/get_connexion.php');
include_once('UTILS/security.php'); // utils for permanent login checking

    //Données passées en POST : S'il s'agit de l'envoi de formulaire, suite au scan fait par l'IPAD
    if (isset($_POST['code']) ) { $pcode = $_POST['code']; ecrireLog('APP', 'INFO', 'pcode :'.$pcode);;}
    if (isset($_POST['input_nombre']) ) { $pnombre = $_POST['input_nombre']; ecrireLog('APP', 'INFO', 'pnombre :'.$pnombre); } else $pnombre = 0;
    if (isset($_POST['input_comment']) ) { $pcomment = $bdd->quote($_POST['input_comment']); ecrireLog('APP', 'INFO', 'pcomment :'.$pcomment); }
    if (isset($_POST['input_nom_bille']) ) { $pbille = $_POST['input_nom_bille']; ecrireLog('APP', 'INFO', 'pbille :'.$pbille); }
    if (isset($_POST['nom_conditionnement']) ) { $pconditionnement = $_POST['nom_conditionnement']; ecrireLog('APP', 'INFO', 'pconditionnement :'.$pconditionnement); }
    if (isset($_POST['nom_marque']) ) { $pmarque = $_POST['nom_marque']; ecrireLog('APP', 'INFO', 'pmarque :'.$pmarque); }
    if (isset($_POST['input_existing']) ) { $pexisting = $_POST['input_existing']; ecrireLog('APP', 'INFO', 'pexisting :'.$pexisting); }
    if (isset($_POST['login']) ) { $login = $_POST['login']; ecrireLog('APP', 'INFO', 'login :'.$login); } else $login = '';
    if (isset($_POST['password']) ) { $password = $_POST['password']; ecrireLog('APP', 'INFO', 'password :'.$password); } else $password = '';

	$logintrouve = false;

	$sql = "SELECT * FROM comptes where LOGIN = '$login'";
	$req = $bdd->prepare($sql);
	$req->execute();

	while ($line = $req->fetch())
	{
		if ($login == $line['LOGIN']) // Si le nom d'utilisateur est trouvé, on vérifie le mdp 
		{
			$s=$line['SALT'];
			$hash = hash('sha256', $password);
			$pwd = hash('sha256', $s . $hash);
			if ($pwd != $line['PASSWORD'])
			{
				retournerErreur( 401 , 05, 'ANDROID_SCAN.PHP| mot de passe incorrect pour ce login');
				exit();
			}
			$logintrouve=true;
		}
	}
	if (!$logintrouve)
	{
		retournerErreur( 401 , 06, 'ANDROID_SCAN.PHP| login inconnu');
		exit();
	}

	if (isset($pexisting) && ($pexisting > 0) ) {
		$sql = 'UPDATE sac_marque_billes_conditionnement set NOMBRE=NOMBRE+'.$pnombre.' where ID_SAC_MARQUE_BILLES_CONDITIONNEMENT = '.$pexisting;
		$req = $bdd->prepare($sql); //METTRE A JOUR le scan sélectionner en rajoutant 1 si sélection
		ecrireLog('SQL', 'INFO', 'ANDROID_SCAN.PHP| REQUETE UPDATE EXISTING= '.$sql);
		if ($req->execute())
		{
			$req = $bdd->prepare("DELETE FROM sac_marque_billes_conditionnement where CODE_BARRE = '$pcode'  and ID_MARQUE_BILLES_CONDITIONNEMENT = 0"); //EFFACER LE SCAN SI ON A REUTILISE UN SCAN EXISTANT
			$req->execute();
		}
		exit();
	}
	
	$sql='SELECT ID_MARQUE_BILLES_CONDITIONNEMENT 
	FROM marque_billes_conditionnement, marque_billes, billes, marques, conditionnement 
	WHERE billes.NOM=\''.$pbille.'\' 
	AND billes.ID_BILLES=marque_billes.ID_BILLES 
	AND marque_billes.ID_MARQUE_BILLES=marque_billes_conditionnement.ID_MARQUE_BILLES 
	AND marque_billes.ID_MARQUE=marques.ID_MARQUE 
	AND marques.MARQUE=\''.$pmarque.'\' AND marque_billes_conditionnement.ID_CONDITIONNEMENT=conditionnement.ID_CONDITIONNEMENT 
	AND conditionnement.NOM=\''.$pconditionnement.'\'';
		
	$req = $bdd->prepare($sql); //Vérifie l'existence d'un triple bille-marque-conditionnement
	if (!$req->execute()) { retournerErreur( 409 , 03, 'ANDROID_SCAN.PHP| Erreur sur l\'exécution de requète de Vérification d\'existence d\'un triple bille-marque-conditionnement'); exit(); }
	$myid = $req->fetch();
	if (!$myid) { retournerErreur( 409 , 03, 'ANDROID_SCAN.PHP| Erreur sur l\'exécution de requète de Vérification d\'existence d\'un triple bille-marque-conditionnement'); exit(); }
	$req->closeCursor();
	$sql = 'INSERT INTO sac_marque_billes_conditionnement ( CODE_BARRE,ID_MARQUE_BILLES_CONDITIONNEMENT,LOGIN_COMPTE, NOMBRE, COMMENTAIRE  ) VALUES ( \''.$pcode.'\','.$myid['ID_MARQUE_BILLES_CONDITIONNEMENT'].',\''.$login.'\','.$pnombre.','.$pcomment.');';
//	ecrireLog('SQL', 'INFO', 'ANDROID_SCAN.PHP| REQUETE INSERT = '.$sql);
	$req = $bdd->prepare($sql); //CREER La LIGNE
	if ($req->execute())
	{
//		$trace ='CODE : '.$pcode.' mis a jour avec '.$pbille.' et '.$pconditionnement ;
//		ecrireLog('APP', 'INFO', $trace);
	}
	else { retournerErreur( 409 , 04, 'ANDROID_SCAN.PHP| Erreur sur l\'exécution de requète d\'insertion de sachet scanné'); ecrireLog('APP', 'INFO', 'ERREUR POUR INSERER LA MAJ!'); }
	$req->closeCursor();
?>
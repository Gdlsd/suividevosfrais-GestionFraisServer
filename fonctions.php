<?php 
	function connexionPDO(){
		$login="root";
		$mdp="";
		$bd="gsb_frais";
		$serveur= "localhost";
		try{
			$conn = new PDO("mysql:host=$serveur;dbname=$bd", $login, $mdp);;
			return $conn;
		}catch(PDOException $e){
			print "Erreur de connexion PDO";
			die();
		}
	}

	function getMoisCourant()
    {
        return date('m');
    }

    function getAnneeCourante()
    {
        return date('Y');
    }

    function dateFrancaisVersAnglais($maDate)
    {
        @list($jour, $mois, $annee) = explode('/', $maDate);
        return date('Y-m-d', mktime(0, 0, 0, $mois, $jour, $annee));
    }

	function estPremierFraisMois($idVisiteur, $mois)
	{
		$boolReturn = false;
		$cnx = connexionPDO();
		$req = $cnx->prepare(
            'SELECT fichefrais.mois FROM fichefrais '
            . 'WHERE fichefrais.mois = :unMois '
            . 'AND fichefrais.idvisiteur = :unIdVisiteur'
        );
        $req->bindParam(':unMois', $mois, PDO::PARAM_STR);
        $req->bindParam(':unIdVisiteur', $idVisiteur, PDO::PARAM_STR);
        $req->execute();

        if (empty($req->fetch())) {
            $boolReturn = true;
        }
        return $boolReturn;
	}	

	function creerNouvellesLignesFrais($idVisiteur, $mois)
	{
		$cnx = connexionPDO();
		$dernierMois = dernierMoisSaisi($idVisiteur);
        $laDerniereFiche = getLesInfosFicheFrais($idVisiteur, $dernierMois);
        $typeMoteur = getTypeVehicule($idVisiteur);
        print(json_encode($typeMoteur));
        if ($laDerniereFiche['idEtat'] == 'CR') {
            majEtatFicheFrais($idVisiteur, $dernierMois, 'CL');
        }
        $req = $cnx->prepare(
            'INSERT INTO fichefrais (idvisiteur,mois,nbjustificatifs,'
            . 'montantvalide,datemodif,idetat, idvehicule) '
            . "VALUES (:unIdVisiteur,:unMois,0,0,now(),'CR', :unTypeMoteur)"
        );
        $req->bindParam(':unIdVisiteur', $idVisiteur, PDO::PARAM_STR);
        $req->bindParam(':unMois', $mois, PDO::PARAM_STR);
        $req->bindParam(':unTypeMoteur', $typeMoteur, PDO::PARAM_STR);
        $req->execute();
        $lesIdFrais = getLesIdFrais();
       // print(json_encode($req));
        foreach ($lesIdFrais as $unIdFrais) {
            $req = $cnx->prepare(
                'INSERT INTO lignefraisforfait (idvisiteur,mois,'
                . 'idfraisforfait,quantite) '
                . 'VALUES(:unIdVisiteur, :unMois, :idFrais, 0)'
            );
            $req->bindParam(':unIdVisiteur', $idVisiteur, PDO::PARAM_STR);
            $req->bindParam(':unMois', $mois, PDO::PARAM_STR);
            $req->bindParam(':idFrais', $unIdFrais['idfrais'], PDO::PARAM_STR);
            $req->execute();
        }
	}



	function supprimerFraisHFMois($idVisiteur)
    {
        $idMoisCourant = getAnneeCourante() . getMoisCourant();
        $cnx = connexionPdo();
        $req = $cnx->prepare(
            'DELETE FROM lignefraishorsforfait '
            .'WHERE mois = :idMoisCourant '
            .'AND idvisiteur = :unIdVisiteur'
        );
        $req->bindParam(':unIdVisiteur', $idVisiteur, PDO::PARAM_STR);
        $req->bindParam(':idMoisCourant', $idMoisCourant, PDO::PARAM_STR);
        $req->execute();
    }

    function fraisForfaitMoisCourant($idVisiteur)
    {
        $idMoisCourant = getAnneeCourante() . getMoisCourant();
        $cnx = connexionPdo();
        $req = $cnx->prepare(
            'SELECT mois, idfraisforfait, quantite FROM lignefraisforfait '
            .'WHERE mois = :idMoisCourant '
            .'AND idvisiteur = :unIdVisiteur'
        );
        $req->bindParam(':unIdVisiteur', $idVisiteur, PDO::PARAM_STR);
        $req->bindParam(':idMoisCourant', $idMoisCourant, PDO::PARAM_STR);
        $req->execute();
        return $req->fetchAll(PDO::FETCH_ASSOC);
    }

	function fraisHfMoisCourant($idVisiteur)
	{
		$idMoisCourant = getAnneeCourante() . getMoisCourant();
		$cnx = connexionPdo();
		$req = $cnx->prepare(
			'SELECT mois, libelle, montant, date FROM lignefraishorsforfait '
			.'WHERE mois = :idMoisCourant '
			.'AND idvisiteur = :unIdVisiteur'
		);
		$req->bindParam(':unIdVisiteur', $idVisiteur, PDO::PARAM_STR);
        $req->bindParam(':idMoisCourant', $idMoisCourant, PDO::PARAM_STR);
		$req->execute();
		return $req->fetchAll(PDO::FETCH_ASSOC);
	}

	function dernierMoisSaisi($idVisiteur)
	{
		$cnx = connexionPDO();
		$req = $cnx->prepare(
            'SELECT MAX(mois) as derniermois '
            . 'FROM fichefrais '
            . 'WHERE fichefrais.idvisiteur = :unIdVisiteur'
        );
        $req->bindParam(':unIdVisiteur', $idVisiteur, PDO::PARAM_STR);
        $req->execute();
        $laLigne = $req->fetch();
        $dernierMois = $laLigne['derniermois'];
        return $dernierMois;
	}

	function getLesInfosFicheFrais($idVisiteur, $derniermois)
	{
		$cnx = connexionPDO();
		$req = $cnx->prepare(
            'SELECT fichefrais.idetat as idEtat, '
            . 'fichefrais.datemodif as dateModif,'
            . 'fichefrais.nbjustificatifs as nbJustificatifs, '
            . 'fichefrais.montantvalide as montantValide, '
            . 'etat.libelle as libEtat '                
            . 'FROM fichefrais '
            . 'INNER JOIN etat ON fichefrais.idetat = etat.id '
            . 'WHERE fichefrais.idvisiteur = :unIdVisiteur '
            . 'AND fichefrais.mois = :unMois'
        );
        $req->bindParam(':unIdVisiteur', $idVisiteur, PDO::PARAM_STR);
        $req->bindParam(':unMois', $mois, PDO::PARAM_STR);
        $req->execute();
        $laLigne = $req->fetch();
        return $laLigne;
	}

	function getLesIdFrais()
	{
		$cnx = connexionPDO();
		$req = $cnx->prepare(
            'SELECT fraisforfait.id as idfrais '
            . 'FROM fraisforfait ORDER BY fraisforfait.id'
        );
        $req->execute();
        return $req->fetchAll();
	}

	function majEtatFicheFrais($idVisiteur, $mois, $etat)
	{
		$cnx = connexionPDO();
		$req = $cnx->prepare(
            'UPDATE fichefrais '
            . 'SET idetat = :unEtat, datemodif = now() '
            . 'WHERE fichefrais.idvisiteur = :unIdVisiteur '
            . 'AND fichefrais.mois = :unMois'
        );
        $req->bindParam(':unEtat', $etat, PDO::PARAM_STR);
        $req->bindParam(':unIdVisiteur', $idVisiteur, PDO::PARAM_STR);
        $req->bindParam(':unMois', $mois, PDO::PARAM_STR);
        $req->execute();
	}

    function majFraisForfait($idVisiteur, $mois, $lesFrais)
    {
        $cnx = connexionPDO();
        $lesCles = array_keys($lesFrais);
        foreach ($lesCles as $unIdFrais) {
            $qte = $lesFrais[$unIdFrais];
            $req= $cnx->prepare(
                'UPDATE lignefraisforfait '
                . 'SET lignefraisforfait.quantite = :uneQte '
                . 'WHERE lignefraisforfait.idvisiteur = :unIdVisiteur '
                . 'AND lignefraisforfait.mois = :unMois '
                . 'AND lignefraisforfait.idfraisforfait = :idFrais'
            );
            $req->bindParam(':uneQte', $qte, PDO::PARAM_INT);
            $req->bindParam(':unIdVisiteur', $idVisiteur, PDO::PARAM_STR);
            $req->bindParam(':unMois', $mois, PDO::PARAM_STR);
            $req->bindParam(':idFrais', $unIdFrais, PDO::PARAM_STR);
            $req->execute();
        }
    }

    function creerNouveauFraisHf($idVisiteur, $mois, $libelle, $dateFr, $montant)
    {
        $cnx = connexionPDO();
        $date = dateFrancaisVersAnglais($dateFr);
        $req = $cnx->prepare(
            'INSERT INTO lignefraishorsforfait '
            . 'VALUES (null, :unIdVisiteur,:unMois, :unLibelle, :uneDateFr, :unMontant, 0)'
        );
        $req->bindParam(':unIdVisiteur', $idVisiteur, PDO::PARAM_STR);
        $req->bindParam(':unMois', $mois, PDO::PARAM_STR);
        $req->bindParam(':unLibelle', $libelle, PDO::PARAM_STR);
        $req->bindParam(':uneDateFr', $date, PDO::PARAM_STR);
        $req->bindParam(':unMontant', $montant, PDO::PARAM_INT);
        $req->execute();
    }

    function getLigneFraisHf($idVisiteur, $mois, $libelle, $dateFr, $montant)
    {
        $cnx = connexionPDO();
        $date = dateFrancaisVersAnglais($dateFr);
        $req = $cnx->prepare(
            'SELECT idvisiteur, mois, libelle, date, montant '
            . 'FROM lignefraishorsforfait '
            . 'WHERE idvisiteur = :unIdVisiteur '
            . 'AND mois = :unMois '
            . 'AND libelle = :unLibelle '
            . 'AND date = :uneDate '
            . 'AND montant = :unMontant'
        );

        $req->bindParam(':unIdVisiteur', $idVisiteur, PDO::PARAM_STR);
        $req->bindParam(':unMois', $mois, PDO::PARAM_STR);
        $req->bindParam(':unLibelle', $libelle, PDO::PARAM_STR);
        $req->bindParam(':uneDate', $date, PDO::PARAM_STR);
        $req->bindParam(':unMontant', $montant, PDO::PARAM_INT);
        $req->execute();
        return $req->fetch();
    }


    function getTypeVehicule($idVisiteur)
    {
        $cnx = connexionPDO();
        $requetePrepare = $cnx->prepare(
            'SELECT vehicule.id '
            . 'FROM vehicule JOIN visiteur ON vehicule.id = visiteur.typevehicule '
            . 'WHERE visiteur.id = :unVisiteur'
        );
        $requetePrepare->bindParam(':unVisiteur', $idVisiteur, PDO::PARAM_STR);
        $requetePrepare->execute();
        $typeMoteur = $requetePrepare->fetch();
        return $typeMoteur['id'];
    }
?>
<?php
include 'fonctions.php';
include 'outils.php';
//contrôle de réception de paramètre
if(isset($_REQUEST["operation"])){

    //Si l'utilisateur souhaite s'authentifier
    if($_REQUEST["operation"] == "authentification") {
        try{
            //récupération des données en post
            $lesdonnees = $_REQUEST['lesdonnees'];
            $donnees = json_decode($lesdonnees);
            $login = $donnees[0];
            $mdp = $donnees[1];
            //Contrôle login/mdp dans la base de donnée et retour de l'id, nom et prenom
            print("authentification%");
            $cnx = connexionPDO();

            //Récupération du mot de passe
            $recupMdp = $cnx->prepare(
                'SELECT visiteur.mdp AS mdp FROM visiteur WHERE login = "' . $login .'"');
            $recupMdp->execute();
            $leMdp = $recupMdp->fetch(PDO::FETCH_ASSOC);


            //Si le mot de passe entré équivaut au mot de passe scripté
            if(password_verify($mdp, $leMdp['mdp'])){

                //Récupération et envoi de l'id, nom et prénom du visiteur
                $req = $cnx->prepare(
                    'SELECT visiteur.id, visiteur.nom, visiteur.prenom FROM visiteur WHERE login = "' . $login .'"'
                );
                $req->execute();
                $ligne = $req->fetch(PDO::FETCH_ASSOC);

                print(json_encode($ligne));
            }
            else
            {
                print(json_encode('erreurLogin'));
            }
        }catch(PDOException $e){
            print "Erreur !%".$e->getMessage();
            die();
        }
    }

    //Récupération des données distantes d'un utilisateur pour un mois donné
    if($_REQUEST["operation"] == "recupFrais") {
        //récupération des données en post
        $lesdonnees = $_REQUEST['lesdonnees'];
        $donnees    = json_decode(utf8_decode($lesdonnees), true);
        $idVisiteur = $donnees[0];

        print("recupFrais%");//Retour des frais de la base de données vers le programme Android

        //Formatage d'un tableau de frais forfait du mois
        $recupFraisForfait = fraisForfaitMoisCourant($idVisiteur);

        if(!empty($recupFraisForfait))
        {
            $etape  =   $recupFraisForfait[0]["quantite"];
            $km     =   $recupFraisForfait[1]["quantite"];
            $nuitee =   $recupFraisForfait[2]["quantite"];
            $repas  =   $recupFraisForfait[3]["quantite"];

            //Formatage d'un tableau de frais HF du mois
            $fraisHfBdd = utf8_converter(fraisHfMoisCourant($idVisiteur));

            //Formatage d'un tableau contenant id, annee, mois, frais forfait et frais hors forfait
            $tousFraisDuMois = [
                $idVisiteur,
                "fraisBdd" => [
                    "annee"         => getAnneeCourante(),
                    "mois"          => getMoisCourant(),
                    "etape"         => $etape,
                    "km"            => $km,
                    "nuitee"        => $nuitee,
                    "repas"         => $repas,
                    "lesFraisHf"    => $fraisHfBdd
                ]
            ];
            print(json_encode($tousFraisDuMois));
        }
        else
        {
            print(json_encode('aucunFrais'));
        }


    }

    //Synchronisation des frais de l'appareil vers le serveur
    if($_REQUEST["operation"] == "synchronisation") {
        //récupération des données en post
        $lesdonnees = $_REQUEST['lesdonnees'];
        $donnees    = json_decode(utf8_decode($lesdonnees), true);
        $idVisiteur = $donnees[0];
        $donnees = $donnees[1];

        print("synchronisation%");

        if(isset($donnees))
        {
            foreach($donnees as $ligne)
            {
                //Récupération mois et année
                $mois   = $ligne['mois'];
                $annee  = $ligne['annee'];
                $moisCourant = getMoisCourant();
                $anneeCourante = getAnneeCourante();

                //Formatage de la clé idMois
                if($mois < 10)
                {
                    $idMois = $annee . "0" .$mois;
                }
                else
                {
                    $idMois = $annee . $mois;
                }

                //Contrôle que les frais envoyés ne datent pas de plus d'un an
                if($annee >= $anneeCourante - 1 && $annee <= $anneeCourante)
                {
                    //création d'un array avec les frais forfait
                    $lesFraisForfait = [
                        'ETP'   =>  $ligne['etape'],
                        'KM'    =>  $ligne['km'],
                        'NUI'   =>  $ligne['nuitee'],
                        'REP'   =>  $ligne['repas']
                    ];

                    //Si aucune ligne pour le mois en cours n'a été créée, création d'une ligne vide
                    if(estPremierFraisMois($idVisiteur, $idMois))
                    {
                        creerNouvellesLignesFrais($idVisiteur, $idMois);
                    }

                    //Mise à jour de la ligne de frais forfait
                    //avec les valeurs saisies dans l'application
                    majFraisForfait($idVisiteur, $idMois, $lesFraisForfait);

                    //Récupération des frais hors forfait
                    $lesFraisHf = $ligne['lesFraisHf'];

                    //Pour chaque ligne de frais
                    foreach($lesFraisHf as $ligneFraisHf)
                    {
                        //Récupération des valeurs
                        $date = $ligneFraisHf['jour']."/".$mois."/".$annee;
                        $libelle = $ligneFraisHf['motif'];
                        $montant = $ligneFraisHf['montant'];
                        $nouveau = $ligneFraisHf['nouveau'];

                        //Si aucune ligne n'est la même que la ligne actuelle
                        if($nouveau == true)
                        {
                            //Création d'une nouvelle ligne de frais Hors Forfait
                            creerNouveauFraisHf($idVisiteur, $idMois, $libelle, $date, $montant);
                        }
                    }
                }
            }
            print("synchroOk");
        }
    }
}
?>

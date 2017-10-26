<?php

namespace Facturation\Model;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Predicate\NotIn;
use Facturation\View\Helper\DateHelper;
use Zend\Db\Sql\Predicate\In;
use Zend\Crypt\PublicKey\Rsa\PublicKey;

class PatientTable {
	protected $tableGateway;
	public function __construct(TableGateway $tableGateway) {
		$this->tableGateway = $tableGateway;
	}
	public function fetchAll() {
		$resultSet = $this->tableGateway->select ();
		return $resultSet;
	}
	public function getPatient($id) {
		$id = ( int ) $id;
		$rowset = $this->tableGateway->select ( array (
				'ID_PERSONNE' => $id
		) );
		$row =  $rowset->current ();
		if (! $row) {
			return null;
		}
		return $row;
	}
	
	public function getInfoPatient($id_personne) {
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pat' => 'patient'))
		->columns( array( '*' ))
		->join(array('pers' => 'personne'), 'pers.id_personne = pat.id_personne' , array('*'))
		->where(array('pat.ID_PERSONNE' => $id_personne));
		
		$stat = $sql->prepareStatementForSqlObject($sQuery);
		$resultat = $stat->execute()->current();
		
		return $resultat;
	}
	
	public function getPhoto($id) {
		$donneesPatient =  $this->getInfoPatient( $id );
	
		$nom = null;
		if($donneesPatient){$nom = $donneesPatient['PHOTO'];}
		if ($nom) {
			return $nom . '.jpg';
		} else {
			return 'identite.jpg';
		}
	}
	
	public function addPatient($donnees , $date_enregistrement , $id_employe){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->insert()
		->into('personne')
		->values( $donnees );
		
		$stat = $sql->prepareStatementForSqlObject($sQuery);
		$id_personne = $stat->execute()->getGeneratedValue();
		
		$this->tableGateway->insert ( array('ID_PERSONNE' => $id_personne , 'DATE_ENREGISTREMENT' => $date_enregistrement , 'ID_EMPLOYE' => $id_employe) );
	}
	
	public  function updatePatient($donnees, $id_patient, $date_enregistrement, $id_employe){
		$this->tableGateway->update( array('DATE_MODIFICATION' => $date_enregistrement, 'ID_EMPLOYE' => $id_employe), array('ID_PERSONNE' => $id_patient) );
	
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->update()
		->table('personne')
		->set( $donnees )
		->where(array('ID_PERSONNE' => $id_patient ));
	
		$stat = $sql->prepareStatementForSqlObject($sQuery);
		$resultat = $stat->execute();
	}
	
	function quoteInto($text, $value, $platform, $count = null)
	{
		if ($count === null) {
			return str_replace('?', $platform->quoteValue($value), $text);
		} else {
			while ($count > 0) {
				if (strpos($text, '?') !== false) {
					$text = substr_replace($text, $platform->quoteValue($value), strpos($text, '?'), 1);
				}
				--$count;
			}
			return $text;
		}
	}
	//Réduire la chaine addresse
	function adresseText($Text){
		$chaine = $Text;
		if(strlen($Text)>36){
			$chaine = substr($Text, 0, 30);
			$nb = strrpos($chaine, ' ');
			$chaine = substr($chaine, 0, $nb);
			$chaine .=' ...';
		}
		return $chaine;
	}
	
	
	public function deletePersonne($id_patient){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql ($db );
		$subselect = $sql->delete ();
		$subselect->from ( 'personne' );
		$subselect->where (array ( 'ID_PERSONNE' => $id_patient ) );
	
		$stat = $sql->prepareStatementForSqlObject($subselect);
		return $stat->execute();
	}
	
	
	public function verifierExisteAdmission($id_patient){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql ($db );
		$subselect = $sql->select ();
		$subselect->from ( array ( 'a' => 'admission_bloc' ) );
		$subselect->columns (array ( '*' ) );
		$subselect->where(array('id_patient' => $id_patient));
		
		$stat = $sql->prepareStatementForSqlObject($subselect);
		return $stat->execute()->current();
	}
	
	//La liste des patients pour verifier leur existance deja dans le syst�me
	//La liste des patients pour verifier leur existance deja dans le syst�me
	public function getListePatientExistanceBD(){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pat' => 'patient'))->columns(array('*'))
		->join(array('p' => 'personne'), 'pat.id_personne = p.id_personne' , array('Nom'=>'NOM','Prenom'=>'PRENOM','Age'=>'AGE','Sexe'=>'SEXE','Adresse'=>'ADRESSE','Nationalite'=>'NATIONALITE_ACTUELLE','Taille'=>'TAILLE','Id'=>'ID_PERSONNE', 'Telephone'=>'TELEPHONE'))
		->order('pat.id_personne DESC');
		
		$result = $sql->prepareStatementForSqlObject($sQuery)->execute();

		$tabDonneesPatient = array();
		$i = 0;
		foreach ($result as $resultat){
			$tabDonneesPatient[$i]['Nom'] = $resultat['Nom'];
			$tabDonneesPatient[$i]['Prenom'] = $resultat['Prenom'];
			$tabDonneesPatient[$i]['Age'] = $resultat['Age'];
			$tabDonneesPatient[$i]['Sexe'] = $resultat['Sexe'];
			$tabDonneesPatient[$i]['Id'] = $resultat['Id'];
			$tabDonneesPatient[$i]['Telephone'] = $resultat['Telephone'];
			
			
			$i++;
		}
		
		return $tabDonneesPatient;
		
	}
	
	public function getListePatient(){
	
		$db = $this->tableGateway->getAdapter();
	
		$aColumns = array('Nom','Prenom','Age','Sexe', 'Adresse', 'Nationalite', 'id', 'id2');
	
		/* Indexed column (used for fast and accurate table cardinality) */
		$sIndexColumn = "id";
	
		/*
		 * Paging
		*/
		$sLimit = array();
		if ( isset( $_GET['iDisplayStart'] ) && $_GET['iDisplayLength'] != '-1' )
		{
			$sLimit[0] = $_GET['iDisplayLength'];
			$sLimit[1] = $_GET['iDisplayStart'];
		}
	
		/*
		 * Ordering
		*/
		if ( isset( $_GET['iSortCol_0'] ) )
		{
			$sOrder = array();
			$j = 0;
			for ( $i=0 ; $i<intval( $_GET['iSortingCols'] ) ; $i++ )
			{
				if ( $_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" )
				{
					$sOrder[$j++] = $aColumns[ intval( $_GET['iSortCol_'.$i] ) ]."
								 	".$_GET['sSortDir_'.$i];
				}
			}
		}
	
		
		
		/*
		 * SQL queries
		*/
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pat' => 'patient'))->columns(array('*'))
		->join(array('p' => 'personne'), 'pat.id_personne = p.id_personne' , array('Nom'=>'NOM','Prenom'=>'PRENOM','Age'=>'AGE','Sexe'=>'SEXE','Adresse'=>'ADRESSE','Nationalite'=>'NATIONALITE_ACTUELLE','Taille'=>'TAILLE','id'=>'ID_PERSONNE', 'id2'=>'ID_PERSONNE'))
		->order('pat.id_personne DESC');
	
		/* Data set length after filtering */
		$stat = $sql->prepareStatementForSqlObject($sQuery);
		$rResultFt = $stat->execute();
		$iFilteredTotal = count($rResultFt);
	
		$rResult = $rResultFt;
	
		$output = array(
				//"sEcho" => intval($_GET['sEcho']),
				//"iTotalRecords" => $iTotal,
				"iTotalDisplayRecords" => $iFilteredTotal,
				"aaData" => array()
		);
	
		/*
		 * $Control pour convertir la date en fran�ais
		*/
		$Control = new DateHelper();
	
		/*
		 * ADRESSE URL RELATIF
		*/
		$baseUrl = $_SERVER['REQUEST_URI'];
		$tabURI  = explode('public', $baseUrl);
	
		/*
		 * Pr�parer la liste
		*/
		foreach ( $rResult as $aRow )
		{
			$row = array();
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				if ( $aColumns[$i] != ' ' )
				{
					/* General output */
					if ($aColumns[$i] == 'Nom'){
						$row[] = "<khass id='nomMaj'>".$aRow[ $aColumns[$i]]."</khass>";
					}
	
					else if ($aColumns[$i] == 'Datenaissance') {
						
						$date_naissance = $aRow[ $aColumns[$i] ];
						if($date_naissance){ $row[] = $Control->convertDate($aRow[ $aColumns[$i] ]); }else{ $row[] = null;}
							
					}
	
					else if ($aColumns[$i] == 'Adresse') {
						$row[] = $this->adresseText($aRow[ $aColumns[$i] ]);
					}
	
					else if ($aColumns[$i] == 'id') {
						$html ="<infoBulleVue> <a href='".$tabURI[0]."public/facturation/info-patient/id_patient/".$aRow[ $aColumns[$i] ]."'>";
						$html .="<img style='display: inline; margin-right: 10%;' src='".$tabURI[0]."public/images_icons/voir2.png' title='d&eacute;tails'></a></infoBulleVue>";
	
						$html .= "<infoBulleVue> <a href='".$tabURI[0]."public/facturation/modifier/id_patient/".$aRow[ $aColumns[$i] ]."'>";
						$html .="<img style='display: inline; margin-right: 10%;' src='".$tabURI[0]."public/images_icons/pencil_16.png' title='Modifier'></a></infoBulleVue>";
	
						
						if(!$this->verifierExisteAdmission($aRow[ $aColumns[$i] ])){
							$html .= "<infoBulleVue> <a id='".$aRow[ $aColumns[$i] ]."' href='javascript:supprimer(".$aRow[ $aColumns[$i] ].");'>";
							$html .="<img style='display: inline;' src='".$tabURI[0]."public/images_icons/symbol_supprimer.png' title='Supprimer'></a></infoBulleVue>";
						}

						
						$row[] = $html;
					}
	
					else {
						$row[] = $aRow[ $aColumns[$i] ];
					}
	
				}
			}
			$output['aaData'][] = $row;
		}
		return $output;
	}
	
	/**
	 * LISTE DE TOUTES LES FEMMES SAUF LES FEMMES DECEDES
	 * @param unknown $id
	 * @return string
	 */
	public function getListeAjouterNaissanceAjax(){
	
		$db = $this->tableGateway->getAdapter();
	
		$aColumns = array('Idpatient','Nom','Prenom','Datenaissance', 'Adresse', 'id');
	
		/* Indexed column (used for fast and accurate table cardinality) */
		$sIndexColumn = "id";
	
		/*
		 * Paging
		*/
		$sLimit = array();
		if ( isset( $_GET['iDisplayStart'] ) && $_GET['iDisplayLength'] != '-1' )
		{
			$sLimit[0] = $_GET['iDisplayLength'];
			$sLimit[1] = $_GET['iDisplayStart'];
		}
	
		/*
		 * Ordering
		*/
		if ( isset( $_GET['iSortCol_0'] ) )
		{
			$sOrder = array();
			$j = 0;
			for ( $i=0 ; $i<intval( $_GET['iSortingCols'] ) ; $i++ )
			{
				if ( $_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" )
				{
					$sOrder[$j++] = $aColumns[ intval( $_GET['iSortCol_'.$i] ) ]."
								 	".$_GET['sSortDir_'.$i];
				}
			}
		}
	
		/*
		 * SQL queries
		*/
		$sql2 = new Sql ($db );
		$subselect1 = $sql2->select ();
		$subselect1->from ( array (
				'd' => 'deces'
		) );
		$subselect1->columns (array (
				'ID_PATIENT'
		) );
	
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pat' => 'patient'))->columns(array('*'))
		->join(array('pers' => 'personne'), 'pat.ID_PERSONNE = pers.ID_PERSONNE' , array('Nom'=>'NOM','Prenom'=>'PRENOM','Datenaissance'=>'DATE_NAISSANCE','Sexe'=>'SEXE','Adresse'=>'ADRESSE','Nationalite'=>'NATIONALITE_ACTUELLE','id'=>'ID_PERSONNE','Idpatient'=>'ID_PERSONNE'))
		->where(array('SEXE' => 'Féminin'))
		->where( array (
				new NotIn ( 'pat.ID_PERSONNE', $subselect1 ),
		) )
		->order('pat.ID_PERSONNE DESC');
		/* Data set length after filtering */
		$stat = $sql->prepareStatementForSqlObject($sQuery);
		$rResultFt = $stat->execute();
		$iFilteredTotal = count($rResultFt);
	
		$rResult = $rResultFt;
	
		$output = array(
				//"sEcho" => intval($_GET['sEcho']),
				//"iTotalRecords" => $iTotal,
				"iTotalDisplayRecords" => $iFilteredTotal,
				"aaData" => array()
		);
	
		/*
		 * $Control pour convertir la date en fran�ais
		*/
		$Control = new DateHelper();
	
		/*
		 * ADRESSE URL RELATIF
		*/
		$baseUrl = $_SERVER['REQUEST_URI'];
		$tabURI  = explode('public', $baseUrl);
	
		/*
		 * Pr�parer la liste
		*/
		foreach ( $rResult as $aRow )
		{
			$row = array();
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				if ( $aColumns[$i] != ' ' )
				{
					/* General output */
					if ($aColumns[$i] == 'Nom'){
						$row[] = "<khass id='nomMaj'>".$aRow[ $aColumns[$i]]."</khass>";
					}
	
					else if ($aColumns[$i] == 'Datenaissance') {
						$row[] = $Control->convertDate($aRow[ $aColumns[$i] ]);
					}
	
					else if ($aColumns[$i] == 'Adresse') {
						$row[] = $this->adresseText($aRow[ $aColumns[$i] ]);
					}
	
					else if ($aColumns[$i] == 'id') {
						$html ="<infoBulleVue> <a href='javascript:visualiser(".$aRow[ $aColumns[$i] ].")' >";
						$html .="<img style='margin-left: 5%; margin-right: 15%;' src='".$tabURI[0]."public/images_icons/voir2.png' title='d&eacute;tails'></a> </infoBulleVue>";
	
						$html .= "<infoBulleVue> <a href='javascript:ajouternaiss(".$aRow[ $aColumns[$i] ].")' >";
						$html .="<img style='display: inline; margin-right: 5%;' src='".$tabURI[0]."public/images_icons/transfert_droite.png' title='suivant'></a> </infoBulleVue>";
	
						$row[] = $html;
					}
	
					else {
						$row[] = $aRow[ $aColumns[$i] ];
					}
	
				}
			}
			$output['aaData'][] = $row;
		}
		return $output;
	}
	
	public function addPersonneNaissance($donnees, $date_enregistrement, $id_employe){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->insert()
		->into('personne')
		->values( $donnees );
	
		$stat = $sql->prepareStatementForSqlObject($sQuery);
		$id_personne = $stat->execute()->getGeneratedValue();
		
		$this->tableGateway->insert ( array('ID_PERSONNE' => $id_personne , 'DATE_ENREGISTREMENT' => $date_enregistrement , 'ID_EMPLOYE' => $id_employe) );
		
		return $id_personne;
	}
	
	
	/**
	 * LISTE NAISSANCES EN AJAX
	 * @param unknown $id
	 * @return string
	 */
	public function getListePatientsAjax(){
	
		$db = $this->tableGateway->getAdapter();
	
		$aColumns = array('Nom','Prenom','Datenaissance','Sexe', 'Adresse', 'Nationalite', 'id');
	
		/* Indexed column (used for fast and accurate table cardinality) */
		$sIndexColumn = "id";
	
		/*
		 * Paging
		*/
		$sLimit = array();
		if ( isset( $_GET['iDisplayStart'] ) && $_GET['iDisplayLength'] != '-1' )
		{
			$sLimit[0] = $_GET['iDisplayLength'];
			$sLimit[1] = $_GET['iDisplayStart'];
		}
	
		/*
		 * Ordering
		*/
		if ( isset( $_GET['iSortCol_0'] ) )
		{
			$sOrder = array();
			$j = 0;
			for ( $i=0 ; $i<intval( $_GET['iSortingCols'] ) ; $i++ )
			{
				if ( $_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" )
				{
					$sOrder[$j++] = $aColumns[ intval( $_GET['iSortCol_'.$i] ) ]."
								 	".$_GET['sSortDir_'.$i];
				}
			}
		}
	
		/*
		 * SQL queries
		*/
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pat' => 'patient'))->columns(array())
		->join(array('pers' => 'personne'), 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('Nom'=>'NOM','Prenom'=>'PRENOM','Datenaissance'=>'DATE_NAISSANCE','Sexe'=>'SEXE','Adresse'=>'ADRESSE','Nationalite'=>'NATIONALITE_ACTUELLE','Taille'=>'TAILLE','id'=>'ID_PERSONNE'))
		->join(array('naiss' => 'naissance') , 'naiss.ID_BEBE = pers.ID_PERSONNE')
		->order('naiss.ID_BEBE DESC');
		/* Data set length after filtering */
		$stat = $sql->prepareStatementForSqlObject($sQuery);
		$rResultFt = $stat->execute();
		$iFilteredTotal = count($rResultFt);
	
		$rResult = $rResultFt;
	
		$output = array(
				//"sEcho" => intval($_GET['sEcho']),
				//"iTotalRecords" => $iTotal,
				"iTotalDisplayRecords" => $iFilteredTotal,
				"aaData" => array()
		);
	
		/*
		 * $Control pour convertir la date en fran�ais
		*/
		$Control = new DateHelper();
	
		/*
		 * ADRESSE URL RELATIF
		*/
		$baseUrl = $_SERVER['REQUEST_URI'];
		$tabURI  = explode('public', $baseUrl);
	
		/*
		 * Pr�parer la liste
		*/
		foreach ( $rResult as $aRow )
		{
			$row = array();
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				if ( $aColumns[$i] != ' ' )
				{
					/* General output */
					if ($aColumns[$i] == 'Nom'){
						$row[] = "<khass id='nomMaj'>".$aRow[ $aColumns[$i]]."</khass>";
					}
	
					else if ($aColumns[$i] == 'Datenaissance') {
						$row[] = $Control->convertDate($aRow[ $aColumns[$i] ]);
					}
	
					else if ($aColumns[$i] == 'Adresse') {
						$row[] = $this->adresseText($aRow[ $aColumns[$i] ]);
					}
	
					else if ($aColumns[$i] == 'id') {
						$html ="<infoBulleVue> <a href='javascript:affichervue(".$aRow[ $aColumns[$i] ].")' >";
						$html .="<img style='display: inline; margin-right: 10%; margin-left: 5%;' src='".$tabURI[0]."public/images_icons/voir2.png' title='d&eacute;tails'></a> </infoBulleVue>";
	
						$html .= "<infoBulleVue> <a href='javascript:modifier(".$aRow[ $aColumns[$i] ].")' >";
						$html .="<img style='display: inline; margin-right: 9%;' src='".$tabURI[0]."public/images_icons/pencil_16.png' title='Modifier'></a> </infoBulleVue>";
	
						$row[] = $html;
					}
	
					else {
						$row[] = $aRow[ $aColumns[$i] ];
					}
	
				}
			}
			$output['aaData'][] = $row;
		}
		return $output;
	}
	
	/**
	 * LISTE DES PATIENTS SAUF LES PATIENTS DECEDES
	 * @param unknown $id
	 * @return string
	 */
	public function getListeDeclarationDecesAjax(){
	
		$db = $this->tableGateway->getAdapter();
	
		$aColumns = array('Idpatient','Nom','Prenom','Datenaissance', 'Adresse', 'id');
	
		/* Indexed column (used for fast and accurate table cardinality) */
		$sIndexColumn = "id";
	
		/*
		 * Paging
		*/
		$sLimit = array();
		if ( isset( $_GET['iDisplayStart'] ) && $_GET['iDisplayLength'] != '-1' )
		{
			$sLimit[0] = $_GET['iDisplayLength'];
			$sLimit[1] = $_GET['iDisplayStart'];
		}
	
		/*
		 * Ordering
		*/
		if ( isset( $_GET['iSortCol_0'] ) )
		{
			$sOrder = array();
			$j = 0;
			for ( $i=0 ; $i<intval( $_GET['iSortingCols'] ) ; $i++ )
			{
				if ( $_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" )
				{
					$sOrder[$j++] = $aColumns[ intval( $_GET['iSortCol_'.$i] ) ]."
								 	".$_GET['sSortDir_'.$i];
				}
			}
		}
	
		/*
		 * SQL queries
		*/
		$sql2 = new Sql ($db );
		$subselect1 = $sql2->select ();
		$subselect1->from ( array (
				'd' => 'deces'
		) );
		$subselect1->columns (array (
				'ID_PATIENT'
		) );
		
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pat' => 'patient'))->columns(array('*'))
		->join(array('pers' => 'personne'), 'pat.ID_PERSONNE = pers.ID_PERSONNE' , array('Nom'=>'NOM','Prenom'=>'PRENOM','Datenaissance'=>'DATE_NAISSANCE','Sexe'=>'SEXE','Adresse'=>'ADRESSE','Nationalite'=>'NATIONALITE_ACTUELLE','id'=>'ID_PERSONNE','Idpatient'=>'ID_PERSONNE'))
		->where( array (
				new NotIn ( 'pat.ID_PERSONNE', $subselect1 ),
		) )
		->order('pat.ID_PERSONNE DESC');
		/* Data set length after filtering */
		$stat = $sql->prepareStatementForSqlObject($sQuery);
		$rResultFt = $stat->execute();
		$iFilteredTotal = count($rResultFt);
	
		$rResult = $rResultFt;
	
		$output = array(
				//"sEcho" => intval($_GET['sEcho']),
				//"iTotalRecords" => $iTotal,
				"iTotalDisplayRecords" => $iFilteredTotal,
				"aaData" => array()
		);
	
		/*
		 * $Control pour convertir la date en fran�ais
		*/
		$Control = new DateHelper();
	
		/*
		 * ADRESSE URL RELATIF
		*/
		$baseUrl = $_SERVER['REQUEST_URI'];
		$tabURI  = explode('public', $baseUrl);
	
		/*
		 * Pr�parer la liste
		*/
		foreach ( $rResult as $aRow )
		{
			$row = array();
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				if ( $aColumns[$i] != ' ' )
				{
					/* General output */
					if ($aColumns[$i] == 'Nom'){
						$row[] = "<khass id='nomMaj'>".$aRow[ $aColumns[$i]]."</khass>";
					}
	
					else if ($aColumns[$i] == 'Datenaissance') {
						$row[] = $Control->convertDate($aRow[ $aColumns[$i] ]);
					}
	
					else if ($aColumns[$i] == 'Adresse') {
						$row[] = $this->adresseText($aRow[ $aColumns[$i] ]);
					}
	
					else if ($aColumns[$i] == 'id') {
						$html ="<infoBulleVue> <a href='javascript:visualiser(".$aRow[ $aColumns[$i] ].")' >";
						$html .="<img style='margin-left: 5%; margin-right: 15%;' src='".$tabURI[0]."public/images_icons/voir2.png' title='d&eacute;tails'></a> </infoBulleVue>";
	
						$html .= "<infoBulleVue> <a href='javascript:declarer(".$aRow[ $aColumns[$i] ].")' >";
						$html .="<img style='display: inline; margin-right: 5%;' src='".$tabURI[0]."public/images_icons/transfert_droite.png' title='suivant'></a> </infoBulleVue>";
	
						$row[] = $html;
					}
	
					else {
						$row[] = $aRow[ $aColumns[$i] ];
					}
	
				}
			}
			$output['aaData'][] = $row;
		}
		return $output;
	}
	
	public function verifierRV($id_personne, $dateAujourdhui){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('rec' => 'rendezvous_consultation'))
		->columns( array( '*' ))
		->join(array('cons' => 'consultation'), 'rec.ID_CONS = cons.ID_CONS' , array())
		->join(array('s' => 'service'), 's.ID_SERVICE = cons.ID_SERVICE' , array('*'))
		->where(array('cons.ID_PATIENT' => $id_personne, 'rec.DATE' => $dateAujourdhui));
	
		$stat = $sql->prepareStatementForSqlObject($sQuery);
		$resultat = $stat->execute()->current();
	
		return $resultat;
	}

	//=============================================================================================================================
	//=============================================================================================================================
	//=============================================================================================================================
	//=============================================================================================================================
	
	public function getServiceParId($id_service){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('s' => 'service'))
		->columns( array( '*' ))
		->where(array('ID_SERVICE' => $id_service));
		
		$stat = $sql->prepareStatementForSqlObject($sQuery);
		$resultat = $stat->execute()->current();
		
		return $resultat;
	}
	
	public function deletePatient($id) {
		$this->tableGateway->delete ( array (
				'ID_PERSONNE' => $id
		) );
	}
	
	public function getPatientsRV($id_service){
		$today = new \DateTime();
		$date = $today->format('Y-m-d');
		
		$adapter = $this->tableGateway->getAdapter();
		$sql = new Sql( $adapter );
		$select = $sql->select();
		$select->from( array(
				'rec' =>  'rendezvous_consultation'
		));
		$select->join(array('cons' => 'consultation'), 'cons.ID_CONS = rec.ID_CONS ', array('*'));
		$select->where( array(
				'rec.DATE' => $date,
				'cons.ID_SERVICE' => $id_service,
		) );
		
		$statement = $sql->prepareStatementForSqlObject( $select );
		$resultat = $statement->execute();
		
		$tab = array(); 
		foreach ($resultat as $result) {
			$tab[$result['ID_PATIENT']] = $result['HEURE'];
		}

		return $tab;
	}
	
	public function tousPatientsAdmis($service, $IdService) {
		$today = new \DateTime();
		$date = $today->format('Y-m-d');
		$adapter = $this->tableGateway->getAdapter ();
		$sql = new Sql ( $adapter );
		$select1 = $sql->select ();
		$select1->from ( array (
				'p' => 'patient'
		) );
		$select1->columns(array () );
		$select1->join(array('pers' => 'personne'), 'pers.ID_PERSONNE = p.ID_PERSONNE', array(
				'Nom' => 'NOM',
				'Prenom' => 'PRENOM',
				'Datenaissance' => 'DATE_NAISSANCE',
				'Sexe' => 'SEXE',
				'Adresse' => 'ADRESSE',
				'Nationalite' => 'NATIONALITE_ACTUELLE',
				'Id' => 'ID_PERSONNE'
		));
		
		$select1->join(array('a' => 'admission'), 'p.ID_PERSONNE = a.id_patient', array('Id_admission' => 'id_admission'));
		$select1->join(array('s' => 'service'), 'a.id_service = s.ID_SERVICE', array('Nomservice' => 'NOM'));
		$select1->where(array('a.date_cons' => $date, 's.NOM' => $service));
		$select1->order('id_admission ASC');
		$statement1 = $sql->prepareStatementForSqlObject ( $select1 );
		$result1 = $statement1->execute ();
		
		$select2 = $sql->select ();
		$select2->from( array( 'cons' => 'consultation'));
		$select2->columns(array('Id' => 'ID_PATIENT', 'Id_cons' => 'ID_CONS', 'Date_cons' => 'DATEONLY',));
		$select2->join(array('cons_eff' => 'consultation_effective'), 'cons_eff.ID_CONS = cons.ID_CONS' , array('*'));
		$select2->where(array('DATEONLY' => $date , 'ID_SERVICE' => $IdService));
		$statement2 = $sql->prepareStatementForSqlObject ( $select2 );
		$result2 = $statement2->execute ();
		$tab = array($result1,$result2);
		return $tab;
	} 
	
	/**
	 * LISTE DES PATIENTS POUR L'ADMISSION DANS UN SERVICE //====**** SAUF LES PATIENTS DECEDES ET CEUX DEJA ADMIS CE JOUR CI
	 * @param unknown $id
	 * @return string
	 */
	public function laListePatientsAjax(){
	
		$db = $this->tableGateway->getAdapter();
	
		$aColumns = array('Idpatient','Nom','Prenom','Age', 'Adresse', 'id');
	
		/* Indexed column (used for fast and accurate table cardinality) */
		$sIndexColumn = "id";
	
		/*
		 * Paging
		*/
		$sLimit = array();
		if ( isset( $_GET['iDisplayStart'] ) && $_GET['iDisplayLength'] != '-1' )
		{
			$sLimit[0] = $_GET['iDisplayLength'];
			$sLimit[1] = $_GET['iDisplayStart'];
		}
	
		/*
		 * Ordering
		*/
		if ( isset( $_GET['iSortCol_0'] ) )
		{
			$sOrder = array();
			$j = 0;
			for ( $i=0 ; $i<intval( $_GET['iSortingCols'] ) ; $i++ )
			{
				if ( $_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" )
				{
					$sOrder[$j++] = $aColumns[ intval( $_GET['iSortCol_'.$i] ) ]."
								 	".$_GET['sSortDir_'.$i];
				}
			}
		}
	
		/*
		 * SQL queries
		*/
		$sql2 = new Sql ($db );
		$subselect1 = $sql2->select ();
		$subselect1->from ( array (
				'd' => 'deces'
		) );
		$subselect1->columns (array (
				'id_patient'
		) );
	
		$date = new \DateTime ("now");
		$dateDuJour = $date->format ( 'Y-m-d' );
		
		$sql3 = new Sql ($db);
		$subselect2 = $sql3->select ();
		$subselect2->from ('admission');
		$subselect2->columns ( array (
				'id_patient'
		) );
		$subselect2->where ( array (
				'date_cons' => $dateDuJour
		) );
		
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pat' => 'patient'))->columns(array())
		->join(array('pers' => 'personne'), 'pat.ID_PERSONNE = pers.ID_PERSONNE', array('Nom'=>'NOM','Prenom'=>'PRENOM','Age'=>'AGE','Sexe'=>'SEXE','Adresse'=>'ADRESSE','Nationalite'=>'NATIONALITE_ACTUELLE','Taille'=>'TAILLE','id'=>'ID_PERSONNE','Idpatient'=>'ID_PERSONNE'))
		->where( array (
				new NotIn ( 'pat.ID_PERSONNE', $subselect1 ),
				new NotIn ( 'pat.ID_PERSONNE', $subselect2 )
		) )
		->order('pat.ID_PERSONNE DESC');
		
		/* Data set length after filtering */
		$stat = $sql->prepareStatementForSqlObject($sQuery);
		$rResultFt = $stat->execute();
		$iFilteredTotal = count($rResultFt);
	
		$rResult = $rResultFt;
	
		$output = array(
				//"sEcho" => intval($_GET['sEcho']),
				//"iTotalRecords" => $iTotal,
				"iTotalDisplayRecords" => $iFilteredTotal,
				"aaData" => array()
		);
	
		/*
		 * $Control pour convertir la date en fran�ais
		*/
		$Control = new DateHelper();
	
		/*
		 * ADRESSE URL RELATIF
		*/
		$baseUrl = $_SERVER['REQUEST_URI'];
		$tabURI  = explode('public', $baseUrl);
	
		/*
		 * Pr�parer la liste
		*/
		foreach ( $rResult as $aRow )
		{
			$row = array();
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				if ( $aColumns[$i] != ' ' )
				{
					/* General output */
					if ($aColumns[$i] == 'Nom'){
						$row[] = "<khass id='nomMaj'>".$aRow[ $aColumns[$i]]."</khass>";
					}
	
					else if ($aColumns[$i] == 'Datenaissance') {
						$date_naissance = $aRow[ $aColumns[$i] ];
						if($date_naissance){ $row[] = $Control->convertDate($aRow[ $aColumns[$i] ]); }else{ $row[] = null;}
					}
	
					else if ($aColumns[$i] == 'Adresse') {
						$row[] = $this->adresseText($aRow[ $aColumns[$i] ]);
					}
	
					else if ($aColumns[$i] == 'id') {
						$html ="<infoBulleVue> <a href='javascript:visualiser(".$aRow[ $aColumns[$i] ].")' >";
						$html .="<img style='margin-left: 5%; margin-right: 15%;' src='".$tabURI[0]."public/images_icons/voir2.png' title='d&eacute;tails'></a> </infoBulleVue>";
	
						$html .= "<infoBulleVue> <a href='javascript:declarer(".$aRow[ $aColumns[$i] ].")' >";
						$html .="<img style='display: inline; margin-right: 5%;' src='".$tabURI[0]."public/images_icons/transfert_droite.png' title='suivant'></a> </infoBulleVue>";
	
						$row[] = $html;
					}
	
					else {
						$row[] = $aRow[ $aColumns[$i] ];
					}
	
				}
			}
			$output['aaData'][] = $row;
		}
		return $output;
	}
	
	/**
	 * Une consultation pour laquelle tous les actes sont pay�es
	 */
	public function verifierActesPayesEnTotalite($idCons){
		$adapter = $this->tableGateway->getAdapter();
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->columns(array('*'));
		$select->from(array('d'=>'demande_acte'));
		$select->join( array( 'a' => 'actes' ), 'd.idActe = a.id' , array ( '*' ) );
		$select->where(array('d.idCons' => $idCons));
		
		$stat = $sql->prepareStatementForSqlObject($select);
		$result = $stat->execute();
		
		foreach ($result as $resultat){
			if($resultat['reglement'] == 0){
				return false;
			}
		}
		
		return true;
	}
	
	
	/**
	 * LISTE DES PATIENTS POUR Le paiement des actes
	 * @param unknown $id
	 * @return string
	 */
	public function listeDesActesImpayesDesPatientsAjax(){
	
		$db = $this->tableGateway->getAdapter();
	
		$aColumns = array('Idpatient','Nom','Prenom','Datenaissance', 'Adresse', 'id', 'idDemande');
	
		/* Indexed column (used for fast and accurate table cardinality) */
		$sIndexColumn = "id";
	
		/*
		 * Paging
		*/
		$sLimit = array();
		if ( isset( $_GET['iDisplayStart'] ) && $_GET['iDisplayLength'] != '-1' )
		{
			$sLimit[0] = $_GET['iDisplayLength'];
			$sLimit[1] = $_GET['iDisplayStart'];
		}
	
		/*
		 * Ordering
		*/
		if ( isset( $_GET['iSortCol_0'] ) )
		{
			$sOrder = array();
			$j = 0;
			for ( $i=0 ; $i<intval( $_GET['iSortingCols'] ) ; $i++ )
			{
				if ( $_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" )
				{
					$sOrder[$j++] = $aColumns[ intval( $_GET['iSortCol_'.$i] ) ]."
								 	".$_GET['sSortDir_'.$i];
				}
			}
		}
	
		/*
		 * SQL queries
		*/
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pat' => 'patient'))->columns(array('*'))
		->join(array('pers' => 'personne'), 'pat.ID_PERSONNE = pers.ID_PERSONNE' , array('Nom'=>'NOM','Prenom'=>'PRENOM','Datenaissance'=>'DATE_NAISSANCE','Sexe'=>'SEXE','Adresse'=>'ADRESSE','Nationalite'=>'NATIONALITE_ACTUELLE','Taille'=>'TAILLE','id'=>'ID_PERSONNE','Idpatient'=>'ID_PERSONNE'))
		->join(array('cons' => 'consultation'), 'cons.ID_PATIENT = pers.ID_PERSONNE' , array('*') )
		->join(array('dem_act' => 'demande_acte'), 'cons.ID_CONS = dem_act.idCons' , array('*') )
		->order('dem_act.idDemande ASC')
		->group('dem_act.idCons');
	
		/* Data set length after filtering */
		$stat = $sql->prepareStatementForSqlObject($sQuery);
		$rResultFt = $stat->execute(); 
		$iFilteredTotal = count($rResultFt);
	
		$rResult = $rResultFt;
	
		$output = array(
				"iTotalDisplayRecords" => $iFilteredTotal,
				"aaData" => array()
		);
	
		/*
		 * $Control pour convertir la date en francais
		*/
		$Control = new DateHelper();
	
		/*
		 * ADRESSE URL RELATIF
		*/
		$baseUrl = $_SERVER['REQUEST_URI'];
		$tabURI  = explode('public', $baseUrl);
	
		/*
		 * Preparer la liste
		*/
		foreach ( $rResult as $aRow )
		{
			if( $this->verifierActesPayesEnTotalite($aRow['idCons']) == false ){ 

				$row = array();
				for ( $i=0 ; $i<count($aColumns) ; $i++ )
				{
					if ( $aColumns[$i] != ' ' )
					{
						/* General output */
						if ($aColumns[$i] == 'Nom'){
							$row[] = "<khass id='nomMaj'>".$aRow[ $aColumns[$i]]."</khass>";
						}
				
						else if ($aColumns[$i] == 'Datenaissance') {
							$date_naissance = $aRow[ $aColumns[$i] ];
							if($date_naissance){ $row[] = $Control->convertDate($aRow[ $aColumns[$i] ]); }else{ $row[] = null;}
						}
				
						else if ($aColumns[$i] == 'Adresse') {
							$row[] = $this->adresseText($aRow[ $aColumns[$i] ]);
						}
				
						else if ($aColumns[$i] == 'id') {
							$html ="<infoBulleVue> <a href='javascript:visualiser(".$aRow[ $aColumns[$i] ].")' >";
							$html .="<img style='margin-left: 5%; margin-right: 15%;' src='".$tabURI[0]."public/images_icons/voir2.png' title='d&eacute;tails'></a> </infoBulleVue>";
				
							$html .= "<infoBulleVue> <a href='javascript:paiement(".$aRow[ $aColumns[$i] ].",".$aRow[ 'idDemande' ] .",1)' >";
							$html .="<img style='display: inline; margin-right: 5%;' src='".$tabURI[0]."public/images_icons/transfert_droite.png' title='suivant'></a> </infoBulleVue>";
				
							$row[] = $html;
						}
				
						else {
							$row[] = $aRow[ $aColumns[$i] ];
						}
				
					}
				}
				$output['aaData'][] = $row;
			}
			
		}
		return $output;
	}
	
	
	/**
	 * LISTE DES PATIENTS POUR les actes deja pay�s
	 * @param unknown $id
	 * @return string
	 */
	public function listeDesActesPayesDesPatientsAjax(){
	
		$db = $this->tableGateway->getAdapter();
	
		$aColumns = array('Idpatient','Nom','Prenom','Datenaissance', 'Adresse', 'id', 'idDemande');
	
		/* Indexed column (used for fast and accurate table cardinality) */
		$sIndexColumn = "id";
	
		/*
		 * Paging
		*/
		$sLimit = array();
		if ( isset( $_GET['iDisplayStart'] ) && $_GET['iDisplayLength'] != '-1' )
		{
			$sLimit[0] = $_GET['iDisplayLength'];
			$sLimit[1] = $_GET['iDisplayStart'];
		}
	
		/*
		 * Ordering
		*/
		if ( isset( $_GET['iSortCol_0'] ) )
		{
			$sOrder = array();
			$j = 0;
			for ( $i=0 ; $i<intval( $_GET['iSortingCols'] ) ; $i++ )
			{
				if ( $_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" )
				{
					$sOrder[$j++] = $aColumns[ intval( $_GET['iSortCol_'.$i] ) ]."
								 	".$_GET['sSortDir_'.$i];
				}
			}
		}
	
		/*
		 * SQL queries
		*/
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pat' => 'patient'))->columns(array('*'))
		->join(array('pers' => 'personne'), 'pat.ID_PERSONNE = pers.ID_PERSONNE', array('Nom'=>'NOM','Prenom'=>'PRENOM','Datenaissance'=>'DATE_NAISSANCE','Sexe'=>'SEXE','Adresse'=>'ADRESSE','Nationalite'=>'NATIONALITE_ACTUELLE','Taille'=>'TAILLE','id'=>'ID_PERSONNE','Idpatient'=>'ID_PERSONNE'))
		->join(array('cons' => 'consultation'), 'cons.ID_PATIENT = pers.ID_PERSONNE', array('*') )
		->join(array('dem_act' => 'demande_acte'), 'cons.ID_CONS = dem_act.idCons', array('*') )
		->order('dem_act.idDemande DESC')
		->group('dem_act.idCons');
	
		/* Data set length after filtering */
		$stat = $sql->prepareStatementForSqlObject($sQuery);
		$rResultFt = $stat->execute();
		$iFilteredTotal = count($rResultFt);
	
		$rResult = $rResultFt;
	
		$output = array(
				"iTotalDisplayRecords" => $iFilteredTotal,
				"aaData" => array()
		);
	
		/*
		 * $Control pour convertir la date en francais
		*/
		$Control = new DateHelper();
	
		/*
		 * ADRESSE URL RELATIF
		*/
		$baseUrl = $_SERVER['REQUEST_URI'];
		$tabURI  = explode('public', $baseUrl);
	
		/*
		 * Preparer la liste
		*/
		foreach ( $rResult as $aRow )
		{
			if( $this->verifierActesPayesEnTotalite($aRow['idCons']) == true ){
				$row = array();
				for ( $i=0 ; $i<count($aColumns) ; $i++ )
				{
					if ( $aColumns[$i] != ' ' )
					{
						/* General output */
						if ($aColumns[$i] == 'Nom'){
							$row[] = "<khass id='nomMaj'>".$aRow[ $aColumns[$i]]."</khass>";
						}
				
						else if ($aColumns[$i] == 'Datenaissance') {
							$date_naissance = $aRow[ $aColumns[$i] ];
							if($date_naissance){ $row[] = $Control->convertDate($aRow[ $aColumns[$i] ]); }else{ $row[] = null;}
						}
				
						else if ($aColumns[$i] == 'Adresse') {
							$row[] = $this->adresseText($aRow[ $aColumns[$i] ]);
						}
				
						else if ($aColumns[$i] == 'id') {
							$html ="<infoBulleVue> <a href='javascript:visualiser(".$aRow[ $aColumns[$i] ].")' >";
							$html .="<img style='margin-left: 5%; margin-right: 15%;' src='".$tabURI[0]."public/images_icons/voir2.png' title='d&eacute;tails'></a> </infoBulleVue>";
				
							$html .= "<infoBulleVue> <a href='javascript:paiement(".$aRow[ $aColumns[$i] ].",".$aRow[ 'idDemande' ] .",2)' >";
							$html .="<img style='display: inline; margin-right: 5%;' src='".$tabURI[0]."public/images_icons/transfert_droite.png' title='suivant'></a> </infoBulleVue>";
				
							$row[] = $html;
						}
				
						else {
							$row[] = $aRow[ $aColumns[$i] ];
						}
				
					}
				}
				$output['aaData'][] = $row;
			}
		}
		return $output;
	}
	
	
	//Tous les patients qui ont pour ID_PESONNE > 900
	public function tousPatients(){
		$adapter = $this->tableGateway->getAdapter ();
		$sql = new Sql ( $adapter );
		$select = $sql->select ();
		$select->from ( array (
				'p' => 'patient'
		) );
		$select->columns(array (
				'Nom' => 'NOM',
				'Prenom' => 'PRENOM',
				'Datenaissance' => 'DATE_NAISSANCE',
				'Sexe' => 'SEXE',
				'Adresse' => 'ADRESSE',
				'Nationalite' => 'NATIONALITE_ACTUELLE',
				'Taille' => 'TAILLE',
				'Id' => 'ID_PERSONNE'
		) );
		$select->where( array (
				'ID_PERSONNE > 900'
		) );
		$select->order('ID_PERSONNE DESC');

		$stmt = $sql->prepareStatementForSqlObject($select);
		$result = $stmt->execute();
		return $result;
	}

	//le nombre de patients qui ont pour ID_PESONNE > 900
	public function nbPatientSUP900(){
		$adapter = $this->tableGateway->getAdapter ();
		$sql = new Sql ( $adapter );
		$select = $sql->select ('patient');
		$select->columns(array ('ID_PERSONNE'));
		$select->where( array (
				'ID_PERSONNE > 900'
		) );
		$stmt = $sql->prepareStatementForSqlObject($select);
		$result = $stmt->execute();
		return $result->count();
	}
	
	public function listeDeTousLesPays()
	{
		$adapter = $this->tableGateway->getAdapter ();
		$sql = new Sql ( $adapter );
		$select = $sql->select ();
		$select->from(array('p'=>'pays'));
		$select->columns(array ('nom_fr_fr'));
		$select->order('nom_fr_fr ASC');
		$stmt = $sql->prepareStatementForSqlObject($select);
		$result = $stmt->execute();
		foreach ($result as $data) {
			$options[$data['nom_fr_fr']] = $data['nom_fr_fr'];
		}
		return $options;
	}
	
	public function listeServices()
	{
		$adapter = $this->tableGateway->getAdapter ();
		$sql = new Sql ( $adapter );
		$select = $sql->select ();
		$select->from(array('serv'=>'service'));
		$select->columns(array ('ID_SERVICE', 'NOM'));
		$select->order('ID_SERVICE ASC');
		$stmt = $sql->prepareStatementForSqlObject($select);
		$result = $stmt->execute();
		$options = array();
		$options[""] = "";
		foreach ($result as $data) {
			$options[$data['ID_SERVICE']] = $data['NOM'];
		}
		return $options;
	}
	
	public function getTypePersonnel()
	{
		$adapter = $this->tableGateway->getAdapter ();
		$sql = new Sql ( $adapter );
		$select = $sql->select ();
		$select->from(array('t'=>'type_employe'));
		$select->columns(array ('id', 'nom'));
		$select->order('id ASC');
		$stmt = $sql->prepareStatementForSqlObject($select);
		$result = $stmt->execute();
		$options = array();
		$options[""] = "";
		foreach ($result as $data) {
			$options[$data['id']] = $data['nom'];
		}
		return $options;
	}
	
	public function listeHopitaux()
	{
		$adapter = $this->tableGateway->getAdapter();
		$sql = new Sql($adapter);
		$select = $sql->select('hopital');
		$select->order('ID_HOPITAL ASC');
		$stat = $sql->prepareStatementForSqlObject($select);
		$result = $stat->execute();
		foreach ($result as $data) {
			$options[$data['ID_HOPITAL']] = $data['NOM_HOPITAL'];
		}
		return $options;
	}
	
	/**
	 * LISTE DES PATIENTS DECEDES
	 * @param unknown $id
	 * @return string
	 */
	public function getListePatientsDecedesAjax(){
	
		$db = $this->tableGateway->getAdapter();
	
		$aColumns = array('Nom','Prenom','Datenaissance','Sexe', 'Adresse', 'Nationalite', 'id');
	
		/* Indexed column (used for fast and accurate table cardinality) */
		$sIndexColumn = "id";
	
		/*
		 * Paging
		*/
		$sLimit = array();
		if ( isset( $_GET['iDisplayStart'] ) && $_GET['iDisplayLength'] != '-1' )
		{
			$sLimit[0] = $_GET['iDisplayLength'];
			$sLimit[1] = $_GET['iDisplayStart'];
		}
	
		/*
		 * Ordering
		*/
		if ( isset( $_GET['iSortCol_0'] ) )
		{
			$sOrder = array();
			$j = 0;
			for ( $i=0 ; $i<intval( $_GET['iSortingCols'] ) ; $i++ )
			{
				if ( $_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" )
				{
					$sOrder[$j++] = $aColumns[ intval( $_GET['iSortCol_'.$i] ) ]."
								 	".$_GET['sSortDir_'.$i];
				}
			}
		}
	
		/*
		 * SQL queries
		*/
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pat' => 'patient'))->columns(array('Nom'=>'NOM','Prenom'=>'PRENOM','Datenaissance'=>'DATE_NAISSANCE','Sexe'=>'SEXE','Adresse'=>'ADRESSE','Nationalite'=>'NATIONALITE_ACTUELLE','Taille'=>'TAILLE','id'=>'ID_PERSONNE'))
		->join(array('d' => 'deces') , 'd.id_personne = pat.ID_PERSONNE')
		->order('d.date_deces DESC');
		/* Data set length after filtering */
		$stat = $sql->prepareStatementForSqlObject($sQuery);
		$rResultFt = $stat->execute();
		$iFilteredTotal = count($rResultFt);
	
		$rResult = $rResultFt;
	
		$output = array(
				//"sEcho" => intval($_GET['sEcho']),
				//"iTotalRecords" => $iTotal,
				"iTotalDisplayRecords" => $iFilteredTotal,
				"aaData" => array()
		);
	
		/*
		 * $Control pour convertir la date en fran�ais
		*/
		$Control = new DateHelper();
	
		/*
		 * ADRESSE URL RELATIF
		*/
		$baseUrl = $_SERVER['REQUEST_URI'];
		$tabURI  = explode('public', $baseUrl);
	
		/*
		 * Pr�parer la liste
		*/
		foreach ( $rResult as $aRow )
		{
			$row = array();
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				if ( $aColumns[$i] != ' ' )
				{
					/* General output */
					if ($aColumns[$i] == 'Nom'){
						$row[] = "<khass id='nomMaj'>".$aRow[ $aColumns[$i]]."</khass>";
					}
	
					else if ($aColumns[$i] == 'Datenaissance') {
						$row[] = $Control->convertDate($aRow[ $aColumns[$i] ]);
					}
	
					else if ($aColumns[$i] == 'Adresse') {
						$row[] = $this->adresseText($aRow[ $aColumns[$i] ]);
					}
	
					else if ($aColumns[$i] == 'id') {
						$html ="<infoBulleVue> <a href='javascript:affichervue(".$aRow[ $aColumns[$i] ].")' >";
						$html .="<img style='margin-right: 15%;' src='".$tabURI[0]."public/images_icons/voir2.PNG' title='d&eacute;tails'></a> </infoBulleVue>";
	
						$html .= "<infoBulleVue> <a href='javascript:modifierdeces(".$aRow[ $aColumns[$i] ].")' >";
						$html .="<img style='display: inline; margin-right: 15%;' src='".$tabURI[0]."public/images_icons/modifier.PNG' title='Modifier'></a> </infoBulleVue>";
	
						$html .= "<infoBulleVue> <a id='".$aRow[ $aColumns[$i] ]."' href='javascript:envoyer(".$aRow[ $aColumns[$i] ].")'>";
						$html .="<img style='display: inline;' src='".$tabURI[0]."public/images_icons/trash_16.PNG' title='Supprimer'></a> </infoBulleVue>";
	
						$row[] = $html;
					}
	
					else {
						$row[] = $aRow[ $aColumns[$i] ];
					}
	
				}
			}
			$output['aaData'][] = $row;
		}
		return $output;
	}
	
	public function getListePatientsAdmisBloc(){


		$db = $this->tableGateway->getAdapter();
		
		$aColumns = array('Nom','Prenom','Age','Sexe', 'diagnostic', 'NomService', 'id');
		
		/* Indexed column (used for fast and accurate table cardinality) */
		$sIndexColumn = "id";
		
		/*
		 * Paging
		*/
		$sLimit = array();
		if ( isset( $_GET['iDisplayStart'] ) && $_GET['iDisplayLength'] != '-1' )
		{
			$sLimit[0] = $_GET['iDisplayLength'];
			$sLimit[1] = $_GET['iDisplayStart'];
		}
		
		/*
		 * Ordering
		*/
		if ( isset( $_GET['iSortCol_0'] ) )
		{
			$sOrder = array();
			$j = 0;
			for ( $i=0 ; $i<intval( $_GET['iSortingCols'] ) ; $i++ )
			{
				if ( $_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" )
				{
					$sOrder[$j++] = $aColumns[ intval( $_GET['iSortCol_'.$i] ) ]."
								 	".$_GET['sSortDir_'.$i];
				}
			}
		}
		
		$date = (new \DateTime ( 'now' ))->format ( 'Y-m-d' );
		
		/*
		 * SQL queries
		*/
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))->columns(array('Nom'=>'NOM','Prenom'=>'PRENOM','Age'=>'AGE','Sexe'=>'SEXE','Adresse'=>'ADRESSE','Nationalite'=>'NATIONALITE_ACTUELLE','Taille'=>'TAILLE','id'=>'ID_PERSONNE'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('se' => 'service_employe') , 'se.id_employe = a.operateur' , array('*'))
		->join(array('s' => 'service') , 's.ID_SERVICE = se.id_service' , array('NomService' => 'NOM'))
		->order(array('a.date' => 'DESC' , 'a.heure' => 'DESC'));
		//->where(array('a.date' => $date));
		/* Data set length after filtering */
		$stat = $sql->prepareStatementForSqlObject($sQuery);
		$rResultFt = $stat->execute();
		$iFilteredTotal = count($rResultFt);
		
		$rResult = $rResultFt;
		
		$output = array(
				//"sEcho" => intval($_GET['sEcho']),
				//"iTotalRecords" => $iTotal,
				"iTotalDisplayRecords" => $iFilteredTotal,
				"aaData" => array()
		);
		
		/*
		 * $Control pour convertir la date en fran�ais
		*/
		$Control = new DateHelper();
		
		/*
		 * ADRESSE URL RELATIF
		*/
		$baseUrl = $_SERVER['REQUEST_URI'];
		$tabURI  = explode('public', $baseUrl);
		
		/*
		 * Pr�parer la liste
		*/
		foreach ( $rResult as $aRow )
		{
			$row = array();
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				if ( $aColumns[$i] != ' ' )
				{
					/* General output */
					if ($aColumns[$i] == 'Nom'){
						$row[] = "<khass id='nomMaj'>".$aRow[ $aColumns[$i]]."</khass>";
					}
		
					else if ($aColumns[$i] == 'Datenaissance') {
						$date = $aRow[ $aColumns[$i] ];
						if($date){ $date = $Control->convertDate($date); } else { $date = null; }
						$row[] = $date; 
					}
		
					else if ($aColumns[$i] == 'Adresse') {
						$row[] = $this->adresseText($aRow[ $aColumns[$i] ]);
					}
		
					else if ($aColumns[$i] == 'id') {
						$verifPop = 1;
						if(!$this->getProtocoleOperatoire($aRow[ 'id_admission' ])){ $verifPop = 0; }
						
						$html ="<infoBulleVue> <a href='javascript:affichervue(".$aRow[ $aColumns[$i] ].",".$aRow[ 'id_admission' ].",".$verifPop.")' >";
						$html .="<img style='margin-right: 15%;' src='".$tabURI[0]."public/images_icons/voir2.png'></a> </infoBulleVue>";
		
						if(!$this->getProtocoleOperatoire($aRow[ 'id_admission' ])){
							$html .= "<infoBulleVue> <a id='".$aRow[ 'id_admission' ]."' href='javascript:envoyer(".$aRow[ 'id_admission' ].")' >";
							$html .="<img style='display: inline; margin-right: 15%;' src='".$tabURI[0]."public/images_icons/symbol_supprimer.png'></a> </infoBulleVue>";
						}
		
						
						$html .="<span style='display: none;'> ".$aRow[ 'NomService' ]." </span>";
						$html .="<span style='display: none;'> ".$aRow[ 'date' ]." </span>";
						
						$row[] = $html;
					}
		
					else {
						$row[] = $aRow[ $aColumns[$i] ];
					}
		
				}
			}
			$output['aaData'][] = $row;
		}
		return $output;
	}
	
	
	public function getProtocoleOperatoire($id_admission_bloc){
		$db = $this->tableGateway->getAdapter();
		$sql2 = new Sql ($db );
		$subselect1 = $sql2->select ();
		$subselect1->from ( array ( 'p' => 'protocole_operatoire_bloc' ) );
		$subselect1->columns (array ( 'id_admission_bloc' ) );
		$subselect1->where (array ( 'id_admission_bloc' => $id_admission_bloc ) );
		
		$stat = $sql2->prepareStatementForSqlObject($subselect1);
		return $stat->execute()->current();
	}
	
	
	public function deleteAdmission($id_admission){
		$db = $this->tableGateway->getAdapter();
		$sql2 = new Sql ($db );
		$subselect1 = $sql2->delete ();
		$subselect1->from ( 'admission_bloc' );
		$subselect1->where (array ( 'id_admission' => $id_admission ) );
	
		$stat = $sql2->prepareStatementForSqlObject($subselect1);
		return $stat->execute();
	}
	
	
	
	
	public function getListePatientsAdmisBlocOperatoire($idOperateur){
	
	
		$db = $this->tableGateway->getAdapter();
	
		$aColumns = array('Nom','Prenom','Age', 'Sexe', 'diagnostic', 'intervention_prevue', 'id');
	
		/* Indexed column (used for fast and accurate table cardinality) */
		$sIndexColumn = "id";
	
		/*
		 * Paging
		*/
		$sLimit = array();
		if ( isset( $_GET['iDisplayStart'] ) && $_GET['iDisplayLength'] != '-1' )
		{
			$sLimit[0] = $_GET['iDisplayLength'];
			$sLimit[1] = $_GET['iDisplayStart'];
		}
	
		/*
		 * Ordering
		*/
		if ( isset( $_GET['iSortCol_0'] ) )
		{
			$sOrder = array();
			$j = 0;
			for ( $i=0 ; $i<intval( $_GET['iSortingCols'] ) ; $i++ )
			{
				if ( $_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" )
				{
					$sOrder[$j++] = $aColumns[ intval( $_GET['iSortCol_'.$i] ) ]."
								 	".$_GET['sSortDir_'.$i];
				}
			}
		}
	
		$date = (new \DateTime ( 'now' ))->format ( 'Y-m-d' );
		
		$sql2 = new Sql ($db );
		$subselect1 = $sql2->select ();
		$subselect1->from ( array ( 'p' => 'protocole_operatoire_bloc' ) );
		$subselect1->columns (array ( 'id_admission_bloc' ) );
		
		/*
		 * SQL queries
		*/
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))->columns(array('Nom'=>'NOM','Prenom'=>'PRENOM','Age'=>'AGE','Sexe'=>'SEXE','Adresse'=>'ADRESSE','Nationalite'=>'NATIONALITE_ACTUELLE','Taille'=>'TAILLE','id'=>'ID_PERSONNE'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('se' => 'service_employe') , 'se.id_employe = a.operateur' , array('*'))
		->join(array('s' => 'service') , 's.ID_SERVICE = se.id_service' , array('NomService' => 'NOM'))
		->order(array('a.date' => 'ASC' , 'a.heure' => 'ASC'))
		->where(array('a.operateur'=> $idOperateur, new NotIn ( 'a.id_admission', $subselect1 )));
		/* Data set length after filtering */
		$stat = $sql->prepareStatementForSqlObject($sQuery);
		$rResultFt = $stat->execute();
		$iFilteredTotal = count($rResultFt);
	
		$rResult = $rResultFt;
	
		$output = array(
				//"sEcho" => intval($_GET['sEcho']),
				//"iTotalRecords" => $iTotal,
				"iTotalDisplayRecords" => $iFilteredTotal,
				"aaData" => array()
		);
	
		/*
		 * $Control pour convertir la date en fran�ais
		*/
		$Control = new DateHelper();
	
		/*
		 * ADRESSE URL RELATIF
		*/
		$baseUrl = $_SERVER['REQUEST_URI'];
		$tabURI  = explode('public', $baseUrl);
	
		/*
		 * Pr�parer la liste
		*/
		foreach ( $rResult as $aRow )
		{
			$row = array();
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				if ( $aColumns[$i] != ' ' )
				{
					/* General output */
					if ($aColumns[$i] == 'Nom'){
						$row[] = "<khass id='nomMaj'>".$aRow[ $aColumns[$i]]."</khass>";
					}
	
					else if ($aColumns[$i] == 'Datenaissance') {
						$date = $aRow[ $aColumns[$i] ];
						if($date){ $date = $Control->convertDate($date); } else { $date = null; }
						$row[] = $date;
					}
					
					else if ($aColumns[$i] == 'diagnostic') {
						$row[] = '<div id="ajustDiagnostic" title="'.str_replace("'", '\'', $aRow[ $aColumns[$i]]).'">'.$aRow[ $aColumns[$i]].'</div>';
					}
					
					else if ($aColumns[$i] == 'intervention_prevue') {
						$row[] = '<div id="ajustIntervention" title="'.str_replace("'", '\'', $aRow[ $aColumns[$i]]).'">'.$aRow[ $aColumns[$i]].'</div>';
					}
	
					else if ($aColumns[$i] == 'Adresse') {
						$row[] = $this->adresseText($aRow[ $aColumns[$i] ]);
					}
	
					else if ($aColumns[$i] == 'id') {
						$html ="<infoBulleVue> <a href='javascript:affichervue(".$aRow[ $aColumns[$i] ].",".$aRow[ 'id_admission' ].")' >";
						$html .="<img style='margin-right: 15%;' src='".$tabURI[0]."public/images_icons/voir2.png'></a> </infoBulleVue>";
	
						//$html .= "<infoBulleVue> <a id='".$aRow[ 'id_admission' ]."' href='javascript:envoyer(".$aRow[ 'id_admission' ].")' >";
						//$html .="<img style='display: inline; margin-right: 15%;' src='".$tabURI[0]."public/images_icons/symbol_supprimer.png'></a> </infoBulleVue>";
	
						$html .="<span style='display: none;'> ".$aRow[ 'date' ]." </span>";
						
						$row[] = $html;
					}
	
					else {
						$row[] = $aRow[ $aColumns[$i] ];
					}
	
				}
			}
			$output['aaData'][] = $row;
		}
		return $output;
	}
	
	
	
	
	public function getListePatientsOperesBlocOperatoire($idOperateur){
	
	
		$db = $this->tableGateway->getAdapter();
	
		$aColumns = array('Nom','Prenom','Age','Sexe', 'diagnostic', 'intervention_prevue', 'id');
	
		/* Indexed column (used for fast and accurate table cardinality) */
		$sIndexColumn = "id";
	
		/*
		 * Paging
		*/
		$sLimit = array();
		if ( isset( $_GET['iDisplayStart'] ) && $_GET['iDisplayLength'] != '-1' )
		{
			$sLimit[0] = $_GET['iDisplayLength'];
			$sLimit[1] = $_GET['iDisplayStart'];
		}
	
		/*
		 * Ordering
		*/
		if ( isset( $_GET['iSortCol_0'] ) )
		{
			$sOrder = array();
			$j = 0;
			for ( $i=0 ; $i<intval( $_GET['iSortingCols'] ) ; $i++ )
			{
				if ( $_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" )
				{
					$sOrder[$j++] = $aColumns[ intval( $_GET['iSortCol_'.$i] ) ]."
								 	".$_GET['sSortDir_'.$i];
				}
			}
		}
	
		$date = (new \DateTime ( 'now' ))->format ( 'Y-m-d' );
	
		/*
		 * SQL queries
		*/
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))->columns(array('Nom'=>'NOM','Prenom'=>'PRENOM','Age'=>'AGE','Sexe'=>'SEXE','Adresse'=>'ADRESSE','Nationalite'=>'NATIONALITE_ACTUELLE','Taille'=>'TAILLE','id'=>'ID_PERSONNE'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('se' => 'service_employe') , 'se.id_employe = a.operateur' , array('*'))
		->join(array('s' => 'service') , 's.ID_SERVICE = se.id_service' , array('NomService' => 'NOM'))
		->join(array('p' => 'protocole_operatoire_bloc' ) , 'p.id_admission_bloc = a.id_admission' , array('*'))
		->order(array('a.date' => 'DESC' , 'a.heure' => 'DESC'))
		->where(array('a.operateur'=> $idOperateur));
		/* Data set length after filtering */
		$stat = $sql->prepareStatementForSqlObject($sQuery);
		$rResultFt = $stat->execute();
		$iFilteredTotal = count($rResultFt);
	
		$rResult = $rResultFt;
	
		$output = array(
				//"sEcho" => intval($_GET['sEcho']),
				//"iTotalRecords" => $iTotal,
				"iTotalDisplayRecords" => $iFilteredTotal,
				"aaData" => array()
		);
	
		/*
		 * $Control pour convertir la date en fran�ais
		*/
		$Control = new DateHelper();
	
		/*
		 * ADRESSE URL RELATIF
		*/
		$baseUrl = $_SERVER['REQUEST_URI'];
		$tabURI  = explode('public', $baseUrl);
	
		/*
		 * Pr�parer la liste
		*/
		foreach ( $rResult as $aRow )
		{
			$row = array();
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				if ( $aColumns[$i] != ' ' )
				{
					/* General output */
					if ($aColumns[$i] == 'Nom'){
						$row[] = "<khass id='nomMaj'>".$aRow[ $aColumns[$i]]."</khass>";
					}
	
					else if ($aColumns[$i] == 'Datenaissance') {
						$date = $aRow[ $aColumns[$i] ];
						if($date){ $date = $Control->convertDate($date); } else { $date = null; }
						$row[] = $date;
					}
					
					else if ($aColumns[$i] == 'diagnostic') {
						$row[] = '<div id="ajustDiagnostic" title="'.str_replace("'", '\'', $aRow[ $aColumns[$i]]).'">'.$aRow[ $aColumns[$i]].'</div>';
					}
						
					else if ($aColumns[$i] == 'intervention_prevue') {
						$row[] = '<div id="ajustIntervention" title="'.str_replace("'", '\'', $aRow[ $aColumns[$i]]).'">'.$aRow[ $aColumns[$i]].'</div>';
					}
					
					else if ($aColumns[$i] == 'Adresse') {
						$row[] = $this->adresseText($aRow[ $aColumns[$i] ]);
					}
	
					else if ($aColumns[$i] == 'id') {
						$html ="<infoBulleVue> <a href='javascript:affichervue(".$aRow[ $aColumns[$i] ].",".$aRow[ 'id_admission' ].")' >";
						$html .="<img style='margin-right: 15%;' src='".$tabURI[0]."public/images_icons/voir2.png'></a> </infoBulleVue>";
	
						//$html .= "<infoBulleVue> <a id='".$aRow[ 'id_admission' ]."' href='javascript:envoyer(".$aRow[ 'id_admission' ].")' >";
						//$html .="<img style='display: inline; margin-right: 15%;' src='".$tabURI[0]."public/images_icons/symbol_supprimer.png'></a> </infoBulleVue>";
	
						$row[] = $html;
					}
	
					else {
						$row[] = $aRow[ $aColumns[$i] ];
					}
	
				}
			}
			$output['aaData'][] = $row;
		}
		return $output;
	}
	
	
	
	
	//GESTION DES FICHIER MP3
	//GESTION DES FICHIER MP3
	//GESTION DES FICHIER MP3
	public function insererMp3($titre , $nom){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->insert()
		->into('fichier_mp3')
		->columns(array('titre', 'nom'))
		->values(array('titre' => $titre , 'nom' => $nom));
		
		$stat = $sql->prepareStatementForSqlObject($sQuery);
		return $stat->execute();
	}
	
	public function getMp3(){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('f' => 'fichier_mp3'))->columns(array('*'))
		->order('id DESC');
		
		$stat = $sql->prepareStatementForSqlObject($sQuery);
		$result = $stat->execute();
		return $result;
	}
	
	public function supprimerMp3($idLigne){
		$liste = $this->getMp3();
		
		$i=1;
		foreach ($liste as $list){
			if($i == $idLigne){
				unlink('C:\wamp\www\simenss\public\js\plugins\jPlayer-2.9.2\examples\\'.$list['nom']);
				
				$db = $this->tableGateway->getAdapter();
				$sql = new Sql($db);
				$sQuery = $sql->delete()
				->from('fichier_mp3')
				->where(array('id' => $list['id']));
				
				$stat = $sql->prepareStatementForSqlObject($sQuery);
				$stat->execute();
				
				return true;
			}
			$i++;
		}
		return false;
	}
	
	protected function nbAnnees($debut, $fin) {
		$nbSecondes = 60*60*24*365;
		$debut_ts = strtotime($debut);
		$fin_ts = strtotime($fin);
		$diff = $fin_ts - $debut_ts;
		return (int)($diff / $nbSecondes);
	}
	
	//Ce code n'est pas optimal
	//Ce code n'est pas optimal
	public function miseAJourAgePatient($id_personne) {
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pat' => 'patient'))
		->columns( array( '*' ))
		->join(array('pers' => 'personne'), 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->where(array('pat.ID_PERSONNE' => $id_personne));
		$pat = $sql->prepareStatementForSqlObject($sQuery)->execute()->current();
		
 		$today = (new \DateTime())->format('Y-m-d');

 		$db = $this->tableGateway->getAdapter();
 		$sql = new Sql($db);
 		
 		$controle = new DateHelper();
 			
 		if($pat['DATE_NAISSANCE']){
 			
 			//POUR LES AGES AVEC DATE DE NAISSANCE
 			//POUR LES AGES AVEC DATE DE NAISSANCE
 		
 			$age = $this->nbAnnees($pat['DATE_NAISSANCE'], $today);
 			
 			$donnees = array('AGE' => $age, 'DATE_MODIFICATION' => $today);
 			$sQuery = $sql->update()
 			->table('personne')
 			->set( $donnees )
 			->where(array('ID_PERSONNE' => $pat['ID_PERSONNE'] ));
 			$sql->prepareStatementForSqlObject($sQuery)->execute();
 				
 		} else {
 			
 			//POUR LES AGES SANS DATE DE NAISSANCE
 			//POUR LES AGES SANS DATE DE NAISSANCE
 		
 			$age = $this->nbAnnees($controle->convertDateInAnglais($controle->convertDate($pat['DATE_MODIFICATION'])), $today);
 			
 			if($age != 0) {
 				$donnees = array('AGE' => $age+$pat['AGE'], 'DATE_MODIFICATION' =>$today);
 				$sQuery = $sql->update()
 				->table('personne')
 				->set( $donnees )
 				->where(array('ID_PERSONNE' => $pat['ID_PERSONNE'] ));
 				$sql->prepareStatementForSqlObject($sQuery)->execute();
 			}

 		}
		
	}
	
	
	
	
	
	
	
	
	
	///REQUETES STATISTIQUES --- REQUETES STATISTIQUES
	///REQUETES STATISTIQUES --- REQUETES STATISTIQUES
	///REQUETES STATISTIQUES --- REQUETES STATISTIQUES
	///REQUETES STATISTIQUES --- REQUETES STATISTIQUES
	/**
	 * NOMBRE DE PATIENTS ADMIS
	 */
	public function nbPatientAdmis(){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))
		->columns(array('*'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('pob' => 'protocole_operatoire_bloc') , 'pob.id_admission_bloc = a.id_admission' , array('*'));
		
		$requete = $sql->prepareStatementForSqlObject($sQuery)->execute()->count();
		
		return $requete;
	}
	
	/**
	 * NOMBRE DE PATIENTS ADMIS DE SEXE FEMININ
	 */
	public function nbPatientAdmisSexeFem(){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))
		->columns(array('*'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('pob' => 'protocole_operatoire_bloc') , 'pob.id_admission_bloc = a.id_admission' , array('*'))
		->where(array('pers.SEXE' => 'FéMININ'));
	
		$requete = $sql->prepareStatementForSqlObject($sQuery)->execute()->count();
	
		return $requete;
	}
	
	/**
	 * NOMBRE DE PATIENTS ADMIS DE SEXE MASCULIN
	 */
	public function nbPatientAdmisSexeMas(){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))
		->columns(array('*'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('pob' => 'protocole_operatoire_bloc') , 'pob.id_admission_bloc = a.id_admission' , array('*'))
		->where(array('pers.SEXE' => 'MASCULIN'));
	
		$requete = $sql->prepareStatementForSqlObject($sQuery)->execute()->count();
	
		return $requete;
	}
	
	/**
	 * NOMBRE DE PATIENTS OPERES DANS CHAQUE SERVICE
	 */
	public function nbPatientOperesParService(){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))
		->columns(array('*'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('pob' => 'protocole_operatoire_bloc') , 'pob.id_admission_bloc = a.id_admission' , array('*'))
		->join(array('se' => 'service_employe') , 'pob.id_employe = se.id_employe' , array('*'))
		->join(array('s' => 'service') , 's.ID_SERVICE = se.id_service' , array('NomService'=>'NOM'));
	
		$resultat = $sql->prepareStatementForSqlObject($sQuery)->execute();
		
		$services = array();
		$lesServices = array();
		foreach ($resultat as $res){
			$services[] = $res['NomService'];
			if(!in_array($res['NomService'], $lesServices)){
				$lesServices[] = $res['NomService'];
			}
		}
		//$tabValeursCompter = array_count_values($services);
		//Trier en respectant l
		//var_dump(array_values($services)); exit();
		
		return array($lesServices, array_count_values($services));
	}
	

	/**
	 * NOMBRE DE PATIENTS OPERES POUR UN SERVICE DONNE
	 */
	public function nbPatientOperesPourUnService($id_service){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))
		->columns(array('*'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('pob' => 'protocole_operatoire_bloc') , 'pob.id_admission_bloc = a.id_admission' , array('*'))
		->join(array('se' => 'service_employe') , 'pob.id_employe = se.id_employe' , array('*'))
		->join(array('s' => 'service') , 's.ID_SERVICE = se.id_service' , array('NomService'=>'NOM'))
		->join(array('emp' => 'employe') , 'emp.id_personne = se.id_employe' , array('*'))
		->join(array('med' => 'personne') , 'med.ID_PERSONNE = emp.id_personne' , array('NomMedecin'=>'NOM', 'PrenomMedecin'=>'PRENOM'))
		->where(array('s.ID_SERVICE' => $id_service));
	
		$resultat = $sql->prepareStatementForSqlObject($sQuery)->execute();
		
		$nomPrenomMedecin = array();
		$lesMedecins = array();
		foreach ($resultat as $res){
			$prenomNomMedecin = $res['PrenomMedecin'].' '.$res['NomMedecin'];
			$nomPrenomMedecin[] = $prenomNomMedecin;
			if(!in_array($prenomNomMedecin , $lesMedecins)){
				$lesMedecins[] = $prenomNomMedecin;
			}
		}
		
		return array($lesMedecins, array_count_values($nomPrenomMedecin));
	}
	
	
	/**
	 * LISTE DES MEDECINS POUR UN SERVICE DONNE
	 */
	public function listeMedecinsPourUnService($id_service){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))->columns(array('IdMedecin'=>'ID_PERSONNE', 'NomMedecin'=>'NOM', 'PrenomMedecin'=>'PRENOM'))
		->join(array('pob' => 'protocole_operatoire_bloc') , 'pob.id_employe = pers.ID_PERSONNE' , array('*'))
		->join(array('se' => 'service_employe') , 'pob.id_employe = se.id_employe' , array('*'))
		->join(array('emp' => 'employe') , 'emp.id_personne = se.id_employe' , array('*'))
		->join(array('s' => 'service') , 's.ID_SERVICE = se.id_service' , array('NomService'=>'NOM'))
		->where(array('s.ID_SERVICE' => $id_service))
		->group(array('pers.ID_PERSONNE'));
	
		$resultat = $sql->prepareStatementForSqlObject($sQuery)->execute();
	
		$nomPrenomMedecin = array();
		$indices = array();
		$nomPrenom = array();
		foreach ($resultat as $res){
			$prenomNomMedecin = $res['PrenomMedecin'].' '.$res['NomMedecin'];
			$nomPrenomMedecin[$res['IdMedecin']] = $prenomNomMedecin;
			$indices[] = $res['IdMedecin'];
			$nomPrenom[] = $prenomNomMedecin;
		}
	
		return array($indices, $nomPrenom);
	}
	
	
	/**
	 * NOMBRE DE PATIENTS DE SEXE FEMININ OPERES PAR UN MEDECIN DONNE
	 */
	public function nbPatientSexeFemOpererParUnMedecin($id_medecin){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))
		->columns(array('*'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('pob' => 'protocole_operatoire_bloc') , 'pob.id_admission_bloc = a.id_admission' , array('*'))
		->where(array('pers.SEXE' => 'FéMININ', 'pob.id_employe' => $id_medecin));
	
		$requete = $sql->prepareStatementForSqlObject($sQuery)->execute()->count();
	
		return $requete;
	}
	
	/**
	 * NOMBRE DE PATIENTS DE SEXE MASCULIN OPERES PAR UN MEDECIN DONNE
	 */
	public function nbPatientSexeMasOpererParUnMedecin($id_medecin){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))
		->columns(array('*'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('pob' => 'protocole_operatoire_bloc') , 'pob.id_admission_bloc = a.id_admission' , array('*'))
		->where(array('pers.SEXE' => 'MASCULIN', 'pob.id_employe' => $id_medecin));
	
		$requete = $sql->prepareStatementForSqlObject($sQuery)->execute()->count();
	
		return $requete;
	}
	
	/**
	 * LISTE DES PATIENTS OPERES PAR UN MEDECIN DONNE SUIVANT UN INTERVALLE D'AGES
	 */
	public function listePatientsOperesParUnMedecinSuivantIntervalleAges($id_medecin, $age_min, $age_max){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))
		->columns(array('*'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('pob' => 'protocole_operatoire_bloc') , 'pob.id_admission_bloc = a.id_admission' , array('*'))
		->where(array('pob.id_employe' => $id_medecin, 'pers.AGE >= ?' => $age_min, 'pers.AGE <= ?' => $age_max))
		->order('pers.AGE ASC');
	
		$requete = $sql->prepareStatementForSqlObject($sQuery)->execute();
	
		$ages = array();
		$sexes = array();
		
		foreach ($requete as $res){
			$ages [] = $res['AGE'];
			$sexes [] = $res['SEXE'];
		}
		
		return array($ages, array_count_values($sexes), array_values($sexes));
	}
	
	/**
	 * NOMBRE DE PATIENTS OPERES POUR UN MEDECIN DONNE SUIVANT DIFFERENT AGE
	 */
	public function nbPatientOperesSuivantAgeParUnMedecin($id_medecin){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))
		->columns(array('*'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('pob' => 'protocole_operatoire_bloc') , 'pob.id_admission_bloc = a.id_admission' , array('*'))
		->join(array('se' => 'service_employe') , 'pob.id_employe = se.id_employe' , array('*'))
		->join(array('s' => 'service') , 's.ID_SERVICE = se.id_service' , array('NomService'=>'NOM'))
		->join(array('emp' => 'employe') , 'emp.id_personne = se.id_employe' , array('*'))
		->join(array('med' => 'personne') , 'med.ID_PERSONNE = emp.id_personne' , array('NomMedecin'=>'NOM', 'PrenomMedecin'=>'PRENOM'))
		->where(array('emp.id_personne' => $id_medecin));
	
		$resultat = $sql->prepareStatementForSqlObject($sQuery)->execute();
	
		$nomPrenomMedecin = array();
		$lesMedecins = array();
		foreach ($resultat as $res){
			$prenomNomMedecin = $res['PrenomMedecin'].' '.$res['NomMedecin'];
			$nomPrenomMedecin[] = $prenomNomMedecin;
			if(!in_array($prenomNomMedecin , $lesMedecins)){
				$lesMedecins[] = $prenomNomMedecin;
			}
		}
	
		return array($lesMedecins, array_count_values($nomPrenomMedecin));
	}
	
	/**
	 * LE PREMIER PATIENT ET LE PREMIER MEDECIN AYANT ENREGISTRER UNE INTERVENTION
	 */
	/***
	 * $p = 0: c'est le premier
	 * $p = 1: c'est le dernier
	 */
	public function premierDernierPatientOpereMedecinIntervenant($p){
		
		if($p == 0){ $p = 'ASC'; }else{ $p ='DESC'; }
		
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))
		->columns(array('*'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('pob' => 'protocole_operatoire_bloc') , 'pob.id_admission_bloc = a.id_admission' , array('Idprotocole'=> 'id_protocole', 'DateIntervention'=> 'date', 'HeureIntervention'=> 'heure'))
		->join(array('se' => 'service_employe') , 'pob.id_employe = se.id_employe' , array('*'))
		->join(array('s' => 'service') , 's.ID_SERVICE = se.id_service' , array('NomService'=>'NOM'))
		->join(array('emp' => 'employe') , 'emp.id_personne = se.id_employe' , array('*'))
		->join(array('med' => 'personne') , 'med.ID_PERSONNE = emp.id_personne' , array('NomMedecin'=>'NOM', 'PrenomMedecin'=>'PRENOM'))
		->order(array('pob.id_protocole' => $p));
		$resultat = $sql->prepareStatementForSqlObject($sQuery)->execute()->current();
		
		return $resultat;
	}
	
	/**
	 * NOMBRE DE PATIENTS OPERES DANS CHAQUE SERVICE POUR UN INTEVALLE DE DATE DONNEE
	 */
	public function nbPatientOperesParServicePourUnePeriodeDonnee($date_debut, $date_fin){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))
		->columns(array('*'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('pob' => 'protocole_operatoire_bloc') , 'pob.id_admission_bloc = a.id_admission' , array('DateProtocole' => 'date'))
		->join(array('se' => 'service_employe') , 'pob.id_employe = se.id_employe' , array('*'))
		->join(array('s' => 'service') , 's.ID_SERVICE = se.id_service' , array('NomService'=>'NOM'))
		->where(array('pob.date >= ?' => $date_debut , 'pob.date <= ?' => $date_fin));
	
		$resultat = $sql->prepareStatementForSqlObject($sQuery)->execute();
	
		$services = array();
		$lesServices = array();
		foreach ($resultat as $res){
			$services[] = $res['NomService'];
			if(!in_array($res['NomService'], $lesServices)){
				$lesServices[] = $res['NomService'];
			}
		}
		
		return array($lesServices, array_count_values($services));
	}
	
	
	/**
	 * NOMBRE DE PATIENTS OPERES POUR UN SERVICE DONNE POUR UN INTEVALLE DE DATE DONNEE
	 */
	public function nbPatientOperesParUnServicePourUnePeriodeDonnee($id_service, $date_debut, $date_fin){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))
		->columns(array('*'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('pob' => 'protocole_operatoire_bloc') , 'pob.id_admission_bloc = a.id_admission' , array('DateProtocole' => 'date'))
		->join(array('se' => 'service_employe') , 'pob.id_employe = se.id_employe' , array('*'))
		->join(array('s' => 'service') , 's.ID_SERVICE = se.id_service' , array('NomService'=>'NOM'))
		->join(array('emp' => 'employe') , 'emp.id_personne = se.id_employe' , array('*'))
		->join(array('med' => 'personne') , 'med.ID_PERSONNE = emp.id_personne' , array('NomMedecin'=>'NOM', 'PrenomMedecin'=>'PRENOM'))
		->where(array('s.ID_SERVICE' => $id_service, 'pob.date >= ?' => $date_debut , 'pob.date <= ?' => $date_fin));
	
		$resultat = $sql->prepareStatementForSqlObject($sQuery)->execute();
	
		$nomPrenomMedecin = array();
		$lesMedecins = array();
		foreach ($resultat as $res){
			$prenomNomMedecin = $res['PrenomMedecin'].' '.$res['NomMedecin'];
			$nomPrenomMedecin[] = $prenomNomMedecin;
			if(!in_array($prenomNomMedecin , $lesMedecins)){
				$lesMedecins[] = $prenomNomMedecin;
			}
		}
	
		return array($lesMedecins, array_count_values($nomPrenomMedecin));
	}
	
	
	/**
	 * NOMBRE DE PATIENTS DE SEXE FEMININ OPERES PAR UN MEDECIN DONNE A UN INTERVALLE DE DATES DONNEES
	 */
	public function nbPatientSexeFemOpererParUnMedecinPeriodeDonnee($id_medecin, $date_debut, $date_fin){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))
		->columns(array('*'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('pob' => 'protocole_operatoire_bloc') , 'pob.id_admission_bloc = a.id_admission' , array('*'))
		->where(array('pers.SEXE' => 'FéMININ', 'pob.id_employe' => $id_medecin, 'pob.date >= ?' => $date_debut , 'pob.date <= ?' => $date_fin));
	
		$requete = $sql->prepareStatementForSqlObject($sQuery)->execute()->count();
	
		return $requete;
	}
	
	
	/**
	 * NOMBRE DE PATIENTS DE SEXE MASCULIN OPERES PAR UN MEDECIN DONNE A UN INTERVALLE DE DATES DONNEES
	 */
	public function nbPatientSexeMasOpererParUnMedecinPeriodeDonnee($id_medecin, $date_debut, $date_fin){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))
		->columns(array('*'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('pob' => 'protocole_operatoire_bloc') , 'pob.id_admission_bloc = a.id_admission' , array('*'))
		->where(array('pers.SEXE' => 'MASCULIN', 'pob.id_employe' => $id_medecin, 'pob.date >= ?' => $date_debut , 'pob.date <= ?' => $date_fin));
	
		$requete = $sql->prepareStatementForSqlObject($sQuery)->execute()->count();
	
		return $requete;
	}
	
	
	/**
	 * DATE PREMIERE ET DERNIERE INTERVENTION D'UN MEDECIN
	 */
	public function dateInterventionMedecin($id_medecin){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))
		->columns(array('*'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('pob' => 'protocole_operatoire_bloc') , 'pob.id_admission_bloc = a.id_admission' , array('DateProtocole' => 'date'))
		->where(array('pob.id_employe' => $id_medecin ));
	
		$requete = $sql->prepareStatementForSqlObject($sQuery)->execute();
	
		$dateIntervention = array();
		foreach ($requete as $res){
			$dateIntervention [] = $res['DateProtocole'];
		}
		
		return $dateIntervention;
	}
	
	/**
	 * NOMBRE DE PATIENTS DE SEXE FEMININ OPERES PAR UN MEDECIN POUR UN INTERVALLE D'AGE DONNE A UN INTERVALLE DE DATES DONNEES
	 */
	public function nbPatientSexeFemOpererParUnMedecinAgePeriodeDonnee($id_medecin, $age_min, $age_max, $date_debut, $date_fin){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))
		->columns(array('*'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('pob' => 'protocole_operatoire_bloc') , 'pob.id_admission_bloc = a.id_admission' , array('*'))
		->where(array('pers.SEXE' => 'FéMININ', 'pob.id_employe' => $id_medecin, 'pers.AGE >= ?' => $age_min, 'pers.AGE <= ?' => $age_max, 'pob.date >= ?' => $date_debut , 'pob.date <= ?' => $date_fin));
	
		$requete = $sql->prepareStatementForSqlObject($sQuery)->execute()->count();
	
		return $requete;
	}
	
	
	/**
	 * NOMBRE DE PATIENTS DE SEXE MASCULIN OPERES PAR UN MEDECIN POUR UN INTERVALLE D'AGE DONNE A UN INTERVALLE DE DATES DONNEES
	 */
	public function nbPatientSexeMasOpererParUnMedecinAgePeriodeDonnee($id_medecin, $age_min, $age_max, $date_debut, $date_fin){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))
		->columns(array('*'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('pob' => 'protocole_operatoire_bloc') , 'pob.id_admission_bloc = a.id_admission' , array('*'))
		->where(array('pers.SEXE' => 'MASCULIN', 'pob.id_employe' => $id_medecin, 'pers.AGE >= ?' => $age_min, 'pers.AGE <= ?' => $age_max, 'pob.date >= ?' => $date_debut , 'pob.date <= ?' => $date_fin));
	
		$requete = $sql->prepareStatementForSqlObject($sQuery)->execute()->count();
	
		return $requete;
	}
	
	/**
	 * NOMBRE DE PATIENTS OPERES DANS CHAQUE SERVICE POUR UN DIAGNOSTIC DONNE
	 */
	public function nbPatientOperesParServicePourDiagnostic($diagnostic){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))
		->columns(array('*'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('pob' => 'protocole_operatoire_bloc') , 'pob.id_admission_bloc = a.id_admission' , array('*'))
		->join(array('se' => 'service_employe') , 'pob.id_employe = se.id_employe' , array('*'))
		->join(array('s' => 'service') , 's.ID_SERVICE = se.id_service' , array('NomService'=>'NOM'))
		->where(array('a.diagnostic' => $diagnostic));
	
		$resultat = $sql->prepareStatementForSqlObject($sQuery)->execute();
	
		$services = array();
		$lesServices = array();
		foreach ($resultat as $res){
			$services[] = $res['NomService'];
			if(!in_array($res['NomService'], $lesServices)){
				$lesServices[] = $res['NomService'];
			}
		}
	
		return array($lesServices, array_count_values($services));
	}
	
	/**
	 * NOMBRE DE PATIENTS OPERES DANS CHAQUE SERVICE POUR UN DIAGNOSTIC DONNE A UNE DATE_DEBUT ET DATE_FIN DONNEE
	 */
	public function nbPatientOperesParServicePourDiagnosticIntervalleDate($diagnostic, $date_debut, $date_fin){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))
		->columns(array('*'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('pob' => 'protocole_operatoire_bloc') , 'pob.id_admission_bloc = a.id_admission' , array('*'))
		->join(array('se' => 'service_employe') , 'pob.id_employe = se.id_employe' , array('*'))
		->join(array('s' => 'service') , 's.ID_SERVICE = se.id_service' , array('NomService'=>'NOM'))
		->where(array('a.diagnostic' => $diagnostic, 'pob.date >= ?' => $date_debut , 'pob.date <= ?' => $date_fin));
	
		$resultat = $sql->prepareStatementForSqlObject($sQuery)->execute();
	
		$services = array();
		$lesServices = array();
		foreach ($resultat as $res){
			$services[] = $res['NomService'];
			if(!in_array($res['NomService'], $lesServices)){
				$lesServices[] = $res['NomService'];
			}
		}
	
		return array($lesServices, array_count_values($services));
	}
	
	
	/**
	 * NOMBRE DE PATIENTS OPERES POUR UN SERVICE DONNE POUR UN DIAGNOSTIC DONNE
	 */
	public function nbPatientOperesPourUnServicePourUnDiagnostic($id_service, $diagnostic){
		$db = $this->tableGateway->getAdapter();
		$sql = new Sql($db);
		$sQuery = $sql->select()
		->from(array('pers' => 'personne'))
		->columns(array('*'))
		->join(array('pat' => 'patient') , 'pers.ID_PERSONNE = pat.ID_PERSONNE' , array('*'))
		->join(array('a' => 'admission_bloc') , 'a.id_patient = pat.ID_PERSONNE' , array('*'))
		->join(array('pob' => 'protocole_operatoire_bloc') , 'pob.id_admission_bloc = a.id_admission' , array('*'))
		->join(array('se' => 'service_employe') , 'pob.id_employe = se.id_employe' , array('*'))
		->join(array('s' => 'service') , 's.ID_SERVICE = se.id_service' , array('NomService'=>'NOM'))
		->join(array('emp' => 'employe') , 'emp.id_personne = se.id_employe' , array('*'))
		->join(array('med' => 'personne') , 'med.ID_PERSONNE = emp.id_personne' , array('NomMedecin'=>'NOM', 'PrenomMedecin'=>'PRENOM'))
		->where(array('s.ID_SERVICE' => $id_service, 'a.diagnostic' => $diagnostic ));
	
		$resultat = $sql->prepareStatementForSqlObject($sQuery)->execute();
	
		$nomPrenomMedecin = array();
		$lesMedecins = array();
		foreach ($resultat as $res){
			$prenomNomMedecin = $res['PrenomMedecin'].' '.$res['NomMedecin'];
			$nomPrenomMedecin[] = $prenomNomMedecin;
			if(!in_array($prenomNomMedecin , $lesMedecins)){
				$lesMedecins[] = $prenomNomMedecin;
			}
		}
	
		return array($lesMedecins, array_count_values($nomPrenomMedecin));
	}
}
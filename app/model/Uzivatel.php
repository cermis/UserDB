<?php

namespace App\Model;

use Nette,
        DateInterval,
    Nette\Database\Context;



/**
 * @author 
 */
class Uzivatel extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'Uzivatel';

    public function getSeznamUzivatelu()
    {
      return($this->findAll());
    }
    
    public function findUserByFulltext($search, $Uzivatel)
    {
        //mobil a email pouze pro ty co maji prava
        
        
        
        $context = new Context($this->connection);
        $completeMatchId = $context->query("SELECT Uzivatel.id FROM Uzivatel 
                                            LEFT JOIN  IPAdresa ON Uzivatel.id = IPAdresa.Uzivatel_id 
                                            WHERE (
                                            Uzivatel.id = '$search'
                                            OR  IPAdresa.ip_adresa = '$search'
                                            ) LIMIT 1")->fetchField();
        if(!empty($completeMatchId))
        {
            return($this->findBy(array('id' => $completeMatchId)));
        }
        //\Tracy\Dumper::dump($search);
        $partialMatchId = $context->query("SELECT Uzivatel.id FROM Uzivatel 
                                            LEFT JOIN  IPAdresa ON Uzivatel.id = IPAdresa.Uzivatel_id 
                                            WHERE (
                                            Uzivatel.id LIKE '$search%'
                                            OR  IPAdresa.ip_adresa LIKE '$search%'
                                            ) LIMIT 1")->fetchField();
        if(!empty($partialMatchId))
        {
            return($this->findBy(array('id' => $partialMatchId)));
        }
        
        if (!$Uzivatel->isInRole('TECH') && !$Uzivatel->isInRole('VV')) {
            $uid = $Uzivatel->getIdentity()->getId();
            $secureMatchId = $context->query("SELECT Uzivatel.id FROM Uzivatel 
                                            JOIN Ap ON Ap.id = Uzivatel.Ap_id
                                            JOIN SpravceOblasti ON Ap.Oblast_id = SpravceOblasti.Oblast_id
                                            WHERE (
                                            telefon LIKE '$search%'
                                            OR  email LIKE '$search%'
                                            OR  email2 LIKE '$search%'
                                            ) AND (SpravceOblasti.Uzivatel_id = $uid AND od<NOW() AND (do IS NULL OR do>NOW())) LIMIT 1")->fetchField();
            if(!empty($secureMatchId))
            {
                return($this->findBy(array('id' => $secureMatchId)));
            }
        }
        else{
            return $this->findAll()->where("telefon LIKE ? OR email LIKE ? OR email2 LIKE ? OR CONVERT(jmeno USING utf8) LIKE ? OR CONVERT(prijmeni USING utf8) LIKE ? OR CONVERT(ulice_cp USING utf8) LIKE ?", '%'.$search.'%', '%'.$search.'%', '%'.$search.'%', '%'.$search.'%', '%'.$search.'%', '%'.$search.'%')->fetchAll();
        }
        
        
        return($this->findBy(array('id' => 0)));
    }
    
    public function getSeznamUzivateluZAP($idAP)
    {
	    return($this->findBy(array('Ap_id' => $idAP)));
    }
    
    public function getSeznamUIDUzivateluZAP($idAP)
    {
	    return($this->findBy(array('Ap_id' => $idAP))->fetchPairs('id','id'));
    }
    
    public function getSeznamUIDUzivatelu()
    {
	    return($this->findAll()->fetchPairs('id','id'));
    }

    public function getUzivatel($id)
    {
      return($this->find($id));
    }

    /** 
    * Generates a strong password of N length containing at least one lower case letter,
    * one uppercase letter, one digit, and one special character. The remaining characters
    * in the password are chosen at random from those four sets.
    *
    * The available characters in each set are user friendly - there are no ambiguous
    * characters such as i, l, 1, o, 0, etc. This, coupled with the $add_dashes option,
    * makes it much easier for users to manually type or speak their passwords.
    *
    * Note: the $add_dashes option will increase the length of the password by
    * floor(sqrt(N)) characters.
    */
    public function generateStrongPassword($length = 9, $add_dashes = false, $available_sets = 'lud')
    {
        $sets = array();
        if (strpos($available_sets, 'l') !== false) {
            $sets[] = 'abcdefghjkmnpqrstuvwxyz';
        }
        if (strpos($available_sets, 'u') !== false) {
            $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        }
        if (strpos($available_sets, 'd') !== false) {
            $sets[] = '23456789';
        }
        if (strpos($available_sets, 's') !== false) {
            $sets[] = '!@#$%&*?';
        }

        $all = '';
        $password = '';
        foreach($sets as $set)
        {
            $password .= $set[array_rand(str_split($set))];
            $all .= $set;
        }

        $all = str_split($all);
        for ($i = 0; $i < $length - count($sets); $i++) {
            $password .= $all[array_rand($all)];
        }

        $password = str_shuffle($password);

        if (!$add_dashes) {
            return $password;
        }

        $dash_len = floor(sqrt($length));
        $dash_str = '';
        while(strlen($password) > $dash_len)
        {
            $dash_str .= substr($password, 0, $dash_len) . '-';
            $password = substr($password, $dash_len);
        }
        $dash_str .= $password;
        return $dash_str;
    }
    
    public function getNewID()
    {
        $context = new Context($this->connection);
        return $context->query('SELECT t1.id+1 AS Free 
FROM Uzivatel AS t1 
LEFT JOIN Uzivatel AS t2 ON t1.id+1 = t2.id 
WHERE t2.id IS NULL AND t1.id>7370 
ORDER BY t1.id LIMIT 1')->fetchField();
    }
    
    public function getDuplicateEmailArea($email, $id)
    {
        $existujici = $this->findAll()->where('email = ? OR email2 = ?', $email, $email)->where('id != ?', $id)->where('TypClenstvi_id > 1')->fetch();
        if($existujici)
        {
            return $existujici->ref('Ap', 'Ap_id')->jmeno . " (" . $existujici->ref('Ap', 'Ap_id')->id . ")";
        }
        return null;
    }
    
    public function getDuplicatePhoneArea($telefon, $id)
    {
        $existujici = $this->findAll()->where('telefon = ?', $telefon)->where('id != ?', $id)->where('TypClenstvi_id > 1')->fetch();
        if($existujici)
        {
            return $existujici->ref('Ap', 'Ap_id')->jmeno . " (" . $existujici->ref('Ap', 'Ap_id')->id . ")";
        }
        return null;
    }
    
    public function mesicName($indate, $addmonth){
        $date = new Nette\Utils\DateTime($indate);
        $date->add(new \DateInterval('P'.$addmonth.'M'));
        $datestr = $date->format('F');
        
        $aj = array("January","February","March","April","May","June","July","August","September","October","November","December");
        $cz = array("leden","únor","březen","duben","květen","červen","červenec","srpen","září","říjen","listopad","prosinec");
        $datum = str_replace($aj, $cz, $datestr);
        return $datum;
    }
    
    public function mesicDate($indate, $addmonth){
        $date = new Nette\Utils\DateTime($indate);
        $date->add(new \DateInterval('P'.$addmonth.'M'));
        return $date->format('17.m.Y');
    }
}
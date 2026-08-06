<?php
/**************************************************************************
 * This file is part of Blue Material Admin (SourceBans++ fork).
 *
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE and NOTICE in the project root.
 *
 * UI theme under themes/new_box has separate provenance — see NOTICE.
 ***************************************************************************/
if (!defined('IN_SB')) {echo("You should not be here. Only follow links!");die();}

/**
 * Класс-костыль, который не будет "выплёвывать"
 * исключения при фэйлах, а просто вернёт FALSE.
 * Не трогать.
 **/
require_once INCLUDES_PATH . '/SourceQuery/autoload.php';
use xPaw\SourceQuery\SourceQuery;

class CServerControl {
    private $sq;
    
    public function __construct() {
        $this->sq = new SourceQuery();
    }
    
    public function Connect($ip, $port = 27015) {
        try {
            $this->sq->Disconnect();
        } catch (\Throwable $e) {}
        
        try {
            $this->sq->Connect($ip, $port, 2, SourceQuery::SOURCE);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    /* RCON */
    public function AuthRcon($password) {
        try {
            $this->sq->SetRconPassword($password);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    public function SendCommand($cmd) {
        try {
            return $this->sq->Rcon($cmd);
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    /* Queries */
    public function GetInfo() {
        try {
            return $this->sq->GetInfo();
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    public function GetPlayers() {
        try {
            return $this->sq->GetPlayers();
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    public function GetRules() {
        try {
            return $this->sq->GetRules();
        } catch (\Throwable $e) {
            return false;
        }
    }
}

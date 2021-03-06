<?php

declare(strict_types=1);
/*
 * @addtogroup notrigger
 * @{
 *
 * @package       NoTrigger
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2020 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.60
 *
 */

require_once __DIR__ . '/../libs/NoTriggerBase.php';

/**
 * TNoTriggerVar ist eine Klasse welche die Daten einer überwachten Variable enthält.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2020 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       2.60
 *
 * @example <b>Ohne</b>
 */
class TNoTriggerVar
{
    /**
     * IPS-ID der Variable.
     *
     * @var int
     */
    public $VarId = 0;

    /**
     * IPS-ID des Link.
     *
     * @var int
     */
    public $LinkId = 0;

    /**
     * True wenn Variable schon Alarm ausgelöst hat.
     *
     * @var bool
     */
    public $Alert = false;

    /**
     * Erzeugt ein neues Objekt aus TNoTriggerVar.
     *
     * @param int $VarId  IPS-ID der Variable.
     * @param int $LinkId IPS-ID des Link.
     * @param int $Alert  Wert für Alert
     *
     * @return TNoTriggerVar Das erzeugte Objekt.
     */
    public function __construct(int $VarId, int $LinkId, bool $Alert)
    {
        $this->VarId = $VarId;
        $this->LinkId = $LinkId;
        $this->Alert = $Alert;
    }
}

/**
 * TNoTriggerVarList ist eine Klasse welche die Daten aller überwachten Variablen enthält.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2020 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       2.60
 *
 * @example <b>Ohne</b>
 */
class TNoTriggerVarList
{
    /**
     * Array mit allen überwachten Variablen.
     *
     * @var array
     */
    public $Items = [];

    /**
     * Liefert die Daten welche behalten werden müssen.
     */
    public function __sleep()
    {
        return ['Items'];
    }

    /**
     * Fügt einen Eintrag in $Items hinzu.
     *
     * @param TNoTriggerVar $NoTriggerVar Das hinzuzufügende Variablen-Objekt.
     */
    public function Add(TNoTriggerVar $NoTriggerVar)
    {
        $this->Items[] = $NoTriggerVar;
    }

    /**
     * Löscht einen Eintrag aus $Items.
     *
     * @param int $Index Der Index des zu löschenden Items.
     */
    public function Remove(int $Index)
    {
        unset($this->Items[$Index]);
    }

    /**
     * Liefert einen bestimmten Eintrag aus den Items.
     *
     * @param int $Index
     *
     * @return TNoTriggerVar
     */
    public function Get(int $Index)
    {
        return $this->Items[$Index];
    }

    /**
     * Liefert den Index von dem Item mit der entsprechenden IPS-Variablen-ID.
     *
     * @param int $VarId Die zu suchende IPS-ID der Variable.
     *
     * @return int Index des gefundenen Eintrags.
     */
    public function IndexOfVarID(int $VarId)
    {
        foreach ($this->Items as $Index => $NoTriggerVar) {
            if ($NoTriggerVar->VarId == $VarId) {
                return $Index;
            }
        }
        return false;
    }

    /**
     * Liefert den Index von dem Item mit der entsprechenden IPS-Link-ID.
     *
     * @param int $LinkId Die zu suchende IPS-ID des Link.
     *
     * @return int Index des gefundenen Eintrags.
     */
    public function IndexOfLinkID(int $LinkId)
    {
        foreach ($this->Items as $Index => $NoTriggerVar) {
            if ($NoTriggerVar->LinkId == $LinkId) {
                return $Index;
            }
        }
        return false;
    }
}

/**
 * NoTrigger Klasse für die die Überwachung von mehreren Variablen auf fehlende Änderung/Aktualisierung.
 * Erweitert NoTriggerBase.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2020 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       2.60
 *
 * @example <b>Ohne</b>
 *
 * @property int $Alerts Anzahl der Alarme
 * @property int $ActiveVarID Anzahl der aktiven Vars
 * @property TNoTriggerVarList $NoTriggerVarList Liste mit allen Variablen
 */
class NoTriggerGroup extends NoTriggerBase
{
    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('Active', false);
        $this->RegisterPropertyBoolean('MultipleAlert', false);
        $this->RegisterPropertyInteger('Timer', 0);
        $this->RegisterPropertyInteger('ScriptID', 0);
        $this->RegisterPropertyBoolean('HasState', true);
        $this->RegisterPropertyInteger('StartUp', 0);
        $this->RegisterPropertyInteger('CheckMode', 0);

        $this->Alerts = 0;
        $this->ActiveVarID = 0;
        $this->NoTriggerVarList = new TNoTriggerVarList();
        $this->RegisterTimer('NoTrigger', 0, 'NT_TimerFire($_IPS["TARGET"]); ');
    }

    /**
     * Interne Funktion des SDK.
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->GetAllTargets();
                switch ($this->ReadPropertyInteger('StartUp')) {
                    case 0:
                        if ($this->CheckConfig()) {
                            $this->StartTimer();
                        }
                        break;
                    case 1:
                        if ($this->CheckConfig()) {
                            $this->SetTimerInterval('NoTrigger', $this->ReadPropertyInteger('Timer') * 1000);
                        }
                        break;
                }
                $this->UnregisterMessage(0, IPS_KERNELSTARTED);
                break;
            case VM_UPDATE:
                // prüfen ob in liste und auswerten wegen Ruhemeldung wenn Alarm war
                $TriggerVarList = $this->NoTriggerVarList;
                $Index = $TriggerVarList->IndexOfVarID($SenderID);
                if ($Index === false) {
                    return;
                }
                $NoTriggerVar = $TriggerVarList->Get($Index);
                if ($NoTriggerVar->Alert && (($Data[1] == true) || ($this->ReadPropertyInteger('CheckMode') == 0))) {
                    $TriggerVarList->Items[$Index]->Alert = false;
                    $this->NoTriggerVarList = $TriggerVarList;
                    $this->Alerts--;
                    if ($this->Alerts == 0) { //war letzter Alarm dann ruhe senden und status setzen
                        $this->DoScript($NoTriggerVar->VarId, false, true);
                        $this->SetStateVar(false, $NoTriggerVar->VarId);
                    } else { // noch mehr in Alarm, also kein state setzen und...
                        if ($this->ReadPropertyBoolean('MultipleAlert')) { //bei mehrfach auch ruhe sende
                            $this->DoScript($NoTriggerVar->VarId, false, true);
                        }
                    }
                }

                // Var das die aktive Var für den Timer ? Dann Timer neu berechnen
                $ActiveVarID = $this->ActiveVarID;
                if ((($SenderID == $ActiveVarID) || ($ActiveVarID == 0)) && (($Data[1] === true) || ($this->ReadPropertyInteger('CheckMode') == 0))) { //Update von Var welche gerade den Timer steuert
                    $this->StartTimer();         // neue Var für timer festlegen und timer starten
                }
                break;
            case VM_DELETE:
                $this->UnregisterVariableWatch($SenderID);
                // prüfen ob in liste und auswerten wegen Ruhemeldung wenn Alarm war
                $TriggerVarList = $this->NoTriggerVarList;
                $Index = $TriggerVarList->IndexOfVarID($SenderID);
                if ($Index === false) {
                    return;
                }
                $NoTriggerVar = $TriggerVarList->Get($Index);

                //und jetzt gleich prüfen ob vorher alarm und merken
                if ($NoTriggerVar->Alert) {
                    $this->Alerts--;
                    if ($this->Alerts == 0) { //war letzter Alarm dann ruhe senden uns status setzen
                        $this->DoScript($NoTriggerVar->VarId, false, true);
                        $this->SetStateVar(false, $NoTriggerVar->VarId);
                    } else { // noch mehr in Alarm, also kein state setzen und...
                        if ($this->ReadPropertyBoolean('MultipleAlert')) { //bei mehrfach auch ruhe sende
                            $this->DoScript($NoTriggerVar->VarId, false, true);
                        }
                    }
                }
                $TriggerVarList->Remove($Index);
                $this->NoTriggerVarList = $TriggerVarList;
                if (count($TriggerVarList->Items) == 0) {
                    $this->ActiveVarID = 0;
                    $this->SetStatus(IS_EBASE + 3);
                    $this->StopTimer();
                    return;
                }
                $ActiveVarID = $this->ActiveVarID;
                if (($SenderID == $ActiveVarID) || ($ActiveVarID == 0)) {
                    $this->StartTimer();         // neue Var für timer festlegen und timer starten
                }
                break;
            case LM_CHANGETARGET:
                // prüfen ob in liste und auswerten wegen Ruhemeldung wenn Alarm war
                $TriggerVarList = $this->NoTriggerVarList;
                $Index = $TriggerVarList->IndexOfLinkID($SenderID);
                if ($Index === false) {
                    return;
                }
                $NoTriggerVar = $TriggerVarList->Get($Index);
                $this->UnregisterVariableWatch($NoTriggerVar->VarId);
                //und jetzt gleich prüfen ob vorher alarm und merken
                if ($NoTriggerVar->Alert) {
                    $this->Alerts--;
                    if ($this->Alerts == 0) { //war letzter Alarm dann ruhe senden uns status setzen
                        $this->DoScript($NoTriggerVar->VarId, false, true);
                        $this->SetStateVar(false, $NoTriggerVar->VarId);
                    } else { // noch mehr in Alarm, also kein state setzen und...
                        if ($this->ReadPropertyBoolean('MultipleAlert')) { //bei mehrfach auch ruhe sende
                            $this->DoScript($NoTriggerVar->VarId, false, true);
                        }
                    }
                }
                $TriggerVarList->Remove($Index);
                // Prüfen ob Ziel eigener State ist
                if ($this->ReadPropertyBoolean('HasState')) {
                    if ($this->GetIDForIdent('STATE') == $Data[0]) {
                        $this->SetStatus(IS_EBASE + 4); //State ist in den Links
                        $this->StopTimer();
                        return;
                    }
                }
                if ($Data[0] > 0) {
                    $Target = IPS_GetObject($Data[0]);
                    if ($Target['ObjectType'] != OBJECTTYPE_VARIABLE) {
                        $Data[0] = 0;
                    }
                }
                if ($Data[0] == 0) {
                    if (count($TriggerVarList->Items) == 0) {
                        $this->NoTriggerVarList = $TriggerVarList;
                        $this->ActiveVarID = 0;
                        $this->SetStatus(IS_EBASE + 3);
                        $this->StopTimer();
                        return;
                    }
                }
                $NoTriggerVar = new TNoTriggerVar($Data[0], $SenderID, false);
                $TriggerVarList->Add($NoTriggerVar);
                $this->NoTriggerVarList = $TriggerVarList;
                $this->RegisterVariableWatch($Data[0]);

                $ActiveVarID = $this->ActiveVarID;
                if (($NoTriggerVar->VarId == $ActiveVarID) || ($ActiveVarID == 0)) {
                    $this->StartTimer();
                }         // alte Var war aktiv oder gar keine

                break;
            case OM_CHILDADDED:
                $IPSObjekt = IPS_GetObject($Data[0]);
                if (($SenderID != $this->InstanceID) || ($IPSObjekt['ObjectType'] != OBJECTTYPE_LINK)) {
                    return;
                }
                $TriggerVarList = $this->NoTriggerVarList;
                $Index = $TriggerVarList->IndexOfLinkID($Data[0]);
                if ($Index !== false) {
                    return;
                }
                $this->RegisterLinkWatch($Data[0]);
                // Prüfen ob Ziel eigener State ist
                $Link = IPS_GetLink($Data[0]);
                if ($this->ReadPropertyBoolean('HasState')) {
                    if ($this->GetIDForIdent('STATE') == $Link['TargetID']) {
                        $this->SetStatus(IS_EBASE + 4); //State ist in den Links
                        $this->StopTimer();
                        return;
                    }
                }
                if ($Link['TargetID'] > 0) {
                    $Target = IPS_GetObject($Link['TargetID']);
                    if ($Target['ObjectType'] != OBJECTTYPE_VARIABLE) {
                        return;
                    }
                }
                if (count($TriggerVarList->Items) == 0) {
                    $this->SetStatus(IS_ACTIVE);
                }
                $NoTriggerVar = new TNoTriggerVar($Link['TargetID'], $Data[0], false);
                $TriggerVarList->Add($NoTriggerVar);
                $this->NoTriggerVarList = $TriggerVarList;
                $this->RegisterVariableWatch($Link['TargetID']);
                $this->StartTimer();         // neue Var für timer festlegen und timer starten
                break;
            case OM_CHILDREMOVED:
                $TriggerVarList = $this->NoTriggerVarList;

                $Index = $TriggerVarList->IndexOfLinkID($Data[0]);
                $this->UnregisterLinkWatch($Data[0]);
                if ($Index === false) {
                    return;
                }
                $NoTriggerVar = $TriggerVarList->Get($Index);
                $this->UnregisterVariableWatch($NoTriggerVar->VarId);
                //und jetzt gleich prüfen ob vorher alarm und merken
                if ($NoTriggerVar->Alert) {
                    $this->Alerts--;
                    if ($this->Alerts == 0) { //war letzter Alarm dann ruhe senden uns status setzen
                        $this->DoScript($NoTriggerVar->VarId, false, true);
                        $this->SetStateVar(false, $NoTriggerVar->VarId);
                    } else { // noch mehr in Alarm, also kein state setzen und...
                        if ($this->ReadPropertyBoolean('MultipleAlert')) { //bei mehrfach auch ruhe sende
                            $this->DoScript($NoTriggerVar->VarId, false, true);
                        }
                    }
                }
                $TriggerVarList->Remove($Index);
                $this->NoTriggerVarList = $TriggerVarList;
                if (count($TriggerVarList->Items) == 0) {
                    $this->ActiveVarID = 0;
                    $this->SetStatus(IS_EBASE + 3);
                    $this->StopTimer();
                    return;
                }
                $ActiveVarID = $this->ActiveVarID;
                if (($NoTriggerVar->VarId == $ActiveVarID) || ($ActiveVarID == 0)) {
                    $this->StartTimer();         // neue Var für timer festlegen und timer starten
                }

                break;
        }
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        $this->RegisterMessage($this->InstanceID, OM_CHILDADDED);
        $this->RegisterMessage($this->InstanceID, OM_CHILDREMOVED);
        parent::ApplyChanges();

        $this->MaintainVariable('STATE', 'STATE', VARIABLETYPE_BOOLEAN, '~Alert', 0, $this->ReadPropertyBoolean('HasState'));
        if (IPS_GetKernelRunlevel() != KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }
        if ($this->CheckConfig()) {
            $this->StartTimer();
        }
    }

    /**
     * Timer abgelaufen Alarm wird erzeugt.
     */
    public function TimerFire()
    {
        $this->StopTimer();
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if ($this->Alerts == 0) {
            $this->SetStateVar(true, $this->ActiveVarID);
            $this->DoScript($this->ActiveVarID, true, false);
        } else {
            if ($this->ReadPropertyBoolean('MultipleAlert')) {
                $this->DoScript($this->ActiveVarID, true, false);
            }
        }
        $this->Alerts++;
        $TriggerVarList = $this->NoTriggerVarList;
        foreach ($TriggerVarList->Items as $i => $IPSVars) {
            if ($IPSVars->VarId == $this->ActiveVarID) {
                $TriggerVarList->Items[$i]->Alert = true;
            }
        }
        $this->NoTriggerVarList = $TriggerVarList;
        $this->StartTimer();
    }

    //################# PRIVATE

    /**
     * Prüft die Konfiguration.
     *
     * @return bool True bei OK
     */
    private function CheckConfig()
    {
        $temp = true;
        if ($this->ReadPropertyBoolean('Active') == true) {
            if ($this->ReadPropertyInteger('Timer') < 1) {
                $this->SetStatus(IS_EBASE + 2); //Error Timer is Zero
                $temp = false;
            }
            if (count($this->NoTriggerVarList->Items) == 0) {
                $this->SetStatus(IS_EBASE + 3); // kein Children
                $temp = false;
            } else {
                if ($this->ReadPropertyBoolean('HasState')) {
                    if ($this->NoTriggerVarList->IndexOfVarID($this->GetIDForIdent('STATE'))) {
                        $this->SetStatus(IS_EBASE + 4); //State ist in den Links
                        $temp = false;
                    }
                }
            }
            if ($temp) {
                $this->SetStatus(IS_ACTIVE);
            }
        } else {
            $temp = false;
            $this->SetStatus(IS_INACTIVE);
        }
        return $temp;
    }

    /**
     * Startet den Timer bis zum Alarm.
     */
    private function StartTimer()
    {
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->ActiveVarID = 0;
        $NowTime = time();
        $LastTime = $NowTime + 98765; //init wert damit lasttime immer größer als aktuelle zeit
        $TriggerVarList = $this->NoTriggerVarList;
        foreach ($TriggerVarList->Items as $i => $IPSVars) {
            if (!IPS_VariableExists($IPSVars->VarId)) {
                continue;
            }
            if ($IPSVars->Alert === true) {
                continue;
            }
            $Variable = IPS_GetVariable($IPSVars->VarId);
            $TestTime = $Variable['VariableUpdated'];

            if (($TestTime + $this->ReadPropertyInteger('Timer')) < $NowTime) { //alarm da in vergangenheit
                $TriggerVarList->Items[$i]->Alert = true;
                $this->Alerts++;
                if ($this->Alerts == 1) {
                    $this->SetStateVar(true, $IPSVars->VarId);
                    $this->DoScript($IPSVars->VarId, true, false);
                } else {
                    if ($this->ReadPropertyBoolean('MultipleAlert')) {
                        $this->DoScript($IPSVars->VarId, true, false);
                    }
                }
                continue;
            } else {
                if ($TestTime < $LastTime) {
                    $LastTime = $TestTime;
                    $this->ActiveVarID = $IPSVars->VarId;
                }
            }
        }
        $this->NoTriggerVarList = $TriggerVarList;

        if ($this->ActiveVarID == 0) {
            $this->LogMessage($this->Translate('All alarms have fired. Monitoring paused.'), KL_NOTIFY);
            $this->StopTimer();
        } else {
            $TargetTime = $LastTime + $this->ReadPropertyInteger('Timer');
            $DiffTime = $TargetTime - $NowTime;
            $this->SetTimerInterval('NoTrigger', $DiffTime * 1000);
        }
    }

    /**
     * Stopt den Timer.
     */
    private function StopTimer()
    {
        $this->SetTimerInterval('NoTrigger', 0);
    }

    /**
     * Liest alle zu Überwachenden Variablen ein.
     */
    private function GetAllTargets()
    {
        $Links = IPS_GetChildrenIDs($this->InstanceID);
        foreach ($this->NoTriggerVarList->Items as $IPSVar) {
            $this->UnregisterVariableWatch($IPSVar->VarId);
            $this->UnregisterLinkWatch($IPSVar->LinkId);
        }
        $TriggerVarList = new TNoTriggerVarList();

        foreach ($Links as $Link) {
            $Objekt = IPS_GetObject($Link);
            if ($Objekt['ObjectType'] != OBJECTTYPE_LINK) {
                continue;
            }

            $Target = @IPS_GetObject(IPS_GetLink($Link)['TargetID']);
            if ($Target === false) {
                continue;
            }
            if ($Target['ObjectType'] != OBJECTTYPE_VARIABLE) {
                continue;
            }
//      zur Liste hinzufügen und Register auf Variable, Link etc...
            $NoTriggerVar = new TNoTriggerVar($Target['ObjectID'], $Link, false);
            $TriggerVarList->Add($NoTriggerVar);
        }
        $this->NoTriggerVarList = $TriggerVarList;
        foreach ($TriggerVarList->Items as $IPSVar) {
            $this->RegisterVariableWatch($IPSVar->VarId);
            $this->RegisterLinkWatch($IPSVar->LinkId);
        }
    }
}

/* @} */

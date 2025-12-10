<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings for the questioncleaner plugin (German).
 *
 * @package     local_questioncleaner
 * @copyright   2024
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Question Bank Cleaner';
$string['questioncleaner'] = 'Question Bank Cleaner';
$string['questioncleaner:view'] = 'Question Cleaner-Berichte anzeigen';
$string['questioncleaner:cleanup'] = 'Question Cleanup-Operationen durchführen';

// Navigation
$string['reports'] = 'Berichte';
$string['duplicatequestions'] = 'Doppelte Fragen';
$string['unusedquestions'] = 'Nicht verwendete Fragen';
$string['usedquestions'] = 'Verwendete Fragen';
$string['unusedanswers'] = 'Nicht verwendete Antworten';
$string['cleanup'] = 'Bereinigung';

// Statistics
$string['totalquestions'] = 'Gesamtfragen';
$string['duplicatedquestions'] = 'Doppelte Fragen';
$string['unusedquestionscount'] = 'Nicht verwendete Fragen';
$string['usedquestionscount'] = 'Verwendete Fragen';
$string['orphanedanswers'] = 'Verwaiste Antworten';
$string['unusedquestionanswers'] = 'Antworten für nicht verwendete Fragen';

// Actions
$string['check'] = 'Prüfen';
$string['delete'] = 'Löschen';
$string['verify'] = 'Überprüfen';
$string['cleanupselected'] = 'Ausgewählte bereinigen';
$string['selectall'] = 'Alle auswählen';
$string['deselectall'] = 'Alle abwählen';

// Messages
$string['noduplicatedquestions'] = 'Keine doppelten Fragen gefunden';
$string['nounusedquestions'] = 'Keine nicht verwendeten Fragen gefunden';
$string['nousedquestions'] = 'Keine verwendeten Fragen gefunden';
$string['usedquestionsinfo'] = 'Diese Fragen werden derzeit in Quizzen verwendet und können nicht gelöscht werden.';
$string['nounusedanswers'] = 'Keine nicht verwendeten Antworten gefunden';
$string['questionsdeleted'] = 'Fragen gelöscht';
$string['answersdeleted'] = 'Antworten gelöscht';
$string['deletionsuccess'] = 'Löschung erfolgreich abgeschlossen';
$string['deletionfailed'] = 'Löschung fehlgeschlagen';
$string['verificationrequired'] = 'Überprüfung vor dem Löschen erforderlich';
$string['questionsverified'] = 'Fragen als nicht verwendet verifiziert';

// Warnings
$string['warningdeletion'] = 'Warnung: Dies wird die ausgewählten Elemente dauerhaft löschen. Diese Aktion kann nicht rückgängig gemacht werden!';
$string['confirmdeletion'] = 'Sind Sie sicher, dass Sie diese Elemente löschen möchten?';
$string['backuprecommended'] = 'Es wird dringend empfohlen, Ihre Datenbank zu sichern, bevor Sie fortfahren.';

// Course filter
$string['filterbycourse'] = 'Nach Kurs filtern';
$string['allcourses'] = 'Alle Kurse';
$string['selectcourse'] = 'Kurs auswählen';

// Details
$string['questionid'] = 'Fragen-ID';
$string['questionname'] = 'Fragenname';
$string['questiontype'] = 'Typ';
$string['category'] = 'Kategorie';
$string['answerid'] = 'Antwort-ID';
$string['answertext'] = 'Antworttext';
$string['relatedquestion'] = 'Zugehörige Frage';
$string['viewdetails'] = 'Details anzeigen';
$string['load'] = 'Laden';
$string['numberofquestions'] = 'Anzahl der Fragen';
$string['perpage'] = 'Pro Seite';
$string['page'] = 'Seite';
$string['showing'] = 'Zeige';
$string['to'] = 'bis';
$string['of'] = 'von';
$string['results'] = 'Ergebnisse';
$string['first'] = 'Erste';
$string['previous'] = 'Vorherige';
$string['next'] = 'Nächste';
$string['last'] = 'Letzte';
$string['noselected'] = 'Keine Elemente ausgewählt';
$string['failed'] = 'Fehlgeschlagen';
$string['settings'] = 'Einstellungen';
$string['batchsize'] = 'Batch-Größe';
$string['batchsize_desc'] = 'Anzahl der Datensätze, die in jedem Batch während Bereinigungsvorgängen verarbeitet werden';
$string['enableautocleanup'] = 'Automatische Bereinigung aktivieren';
$string['enableautocleanup_desc'] = 'Automatische Bereinigungsaufgaben aktivieren';
$string['information'] = 'Informationen';
$string['quicklinks'] = 'Schnelllinks';
$string['cleanuptask'] = 'Question Bank Bereinigungsaufgabe';

// Progress bar
$string['loadingstatistics'] = 'Statistiken werden geladen...';
$string['loadingtotalquestions'] = 'Gesamtfragen werden geladen...';
$string['loadingduplicatedquestions'] = 'Doppelte Fragen werden geprüft...';
$string['loadingunusedquestions'] = 'Nicht verwendete Fragen werden geprüft...';
$string['loadingorphanedanswers'] = 'Verwaiste Antworten werden geprüft...';
$string['loadingunusedanswers'] = 'Antworten für nicht verwendete Fragen werden geprüft...';
$string['loadingusedquestions'] = 'Anzahl der verwendeten Fragen wird geladen...';
$string['completing'] = 'Wird abgeschlossen...';
$string['errorloadingstatistics'] = 'Fehler beim Laden der Statistiken';

// Detailed statistics
$string['detailedstatistics'] = 'Detaillierte Statistiken';
$string['totaltables'] = 'Gesamttabellen';
$string['totalrecords'] = 'Gesamtdatensätze';
$string['totalsize'] = 'Gesamtgröße';
$string['largesttable'] = 'Größte Tabelle';
$string['tabledetails'] = 'Tabellendetails';
$string['tablename'] = 'Tabellenname';
$string['rows'] = 'Zeilen';
$string['size'] = 'Größe';

// Cache
$string['lastupdated'] = 'Zuletzt aktualisiert';
$string['usingcacheddata'] = 'Verwende gecachte Daten';
$string['nocachedata'] = 'Keine gecachten Daten verfügbar';
$string['refreshstatistics'] = 'Statistiken aktualisieren';
$string['cacherefreshed'] = 'Cache gelöscht. Statistiken werden neu berechnet.';

// Cleanup process
$string['whatgetsdeleted'] = 'Was wird gelöscht';
$string['unusedquestionsdeletion'] = 'Beim Löschen nicht verwendeter Fragen werden die folgenden Daten in dieser Reihenfolge entfernt:';
$string['orphanedanswersdeletion'] = 'Verwaiste Antworten können zu 100% sicher gelöscht werden, da sie mit Fragen verknüpft sind, die nicht mehr existieren. Es werden nur die Antwortdatensätze gelöscht.';
$string['unusedanswersdeletion'] = 'Antworten für nicht verwendete Fragen werden nur gelöscht, nachdem überprüft wurde, dass die Frage in keinem Quiz verwendet wird.';
$string['deletionstep1'] = 'Fragenantworten (question_answers)';
$string['deletionstep2'] = 'Fragentyp-Optionen (qtype_*_options)';
$string['deletionstep3'] = 'Fragenversionen (question_versions)';
$string['deletionstep4'] = 'Question Bank-Einträge (question_bank_entries) - nur wenn keine Versionen mehr vorhanden sind';
$string['deletionstep5'] = 'Fragen selbst (question)';
$string['safetychecks'] = 'Sicherheitsprüfungen';
$string['safetycheck1'] = 'Fragen werden vor dem Löschen gegen question_references überprüft';
$string['safetycheck2'] = 'Doppelte Überprüfung: vor und während des Löschens überprüft';
$string['safetycheck3'] = 'Verwaiste Antworten werden überprüft, um sicherzustellen, dass die Frage gelöscht wurde';
$string['safetycheck4'] = 'Antworten für nicht verwendete Fragen werden überprüft, um sicherzustellen, dass die Frage nicht verwendet wird';
$string['orphanedanswerssafe'] = '100% sicher zu löschen';
$string['orphanedanswerssafedesc'] = 'Diese Antworten sind mit Fragen verknüpft, die nicht mehr existieren. Sie können ohne Risiko sicher gelöscht werden.';
$string['unusedanswerswarning'] = 'Überprüfung erforderlich';
$string['unusedanswerswarningdesc'] = 'Diese Antworten gehören zu nicht verwendeten Fragen. Sie werden vor dem Löschen überprüft, um sicherzustellen, dass die Frage in keinem Quiz verwendet wird.';

// Number formatting
$string['million'] = 'Millionen';
$string['thousand'] = 'Tausend';

// Interactive cleanup
$string['startcleanup'] = 'Bereinigung starten';
$string['stopcleanup'] = 'Bereinigung stoppen';
$string['cleanuptype'] = 'Bereinigungstyp';
$string['cleanuptype_unused'] = 'Nicht verwendete Fragen';
$string['cleanuptype_orphaned'] = 'Verwaiste Antworten';
$string['cleanuptype_unusedanswers'] = 'Antworten für nicht verwendete Fragen';
$string['cleanuptype_duplicate'] = 'Doppelte Fragen';
$string['numberofbatches'] = 'Anzahl der Batches';
$string['processall'] = 'Alle verarbeiten';
$string['deleted'] = 'Gelöscht';
$string['remaining'] = 'Verbleibend';
$string['processing'] = 'Wird verarbeitet...';
$string['cleanupinprogress'] = 'Bereinigung läuft';
$string['cleanupstopped'] = 'Bereinigung vom Benutzer gestoppt';
$string['cleanupcompleted'] = 'Bereinigung abgeschlossen';
$string['cleanupfailed'] = 'Bereinigung fehlgeschlagen';
$string['currentbatch'] = 'Aktueller Batch';
$string['totalbatches'] = 'Gesamtbatches';
$string['selectcleanuptype'] = 'Bitte wählen Sie einen Bereinigungstyp';
$string['invalidbatchsize'] = 'Ungültige Batch-Größe';
$string['invalidnumberofbatches'] = 'Ungültige Anzahl von Batches';


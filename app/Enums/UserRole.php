<?php

namespace App\Enums;

/**
 * Rollen der kiventro B2E-Demo. Bewusst vier klar getrennte Sichten,
 * damit jede Rolle in wenigen Klicks zu ihrem Ziel kommt.
 */
enum UserRole: string
{
    case WebAdmin = 'web-admin';
    case Geschaeftsfuehrer = 'geschaeftsfuehrer';
    case Bereichsleiter = 'bereichsleiter';
    case Nutzer = 'nutzer';

    public function label(): string
    {
        return match ($this) {
            self::WebAdmin => 'Web-Administrator',
            self::Geschaeftsfuehrer => 'Geschäftsführung',
            self::Bereichsleiter => 'Bereichsleitung',
            self::Nutzer => 'Mitarbeiter',
        };
    }

    /**
     * Kurzbeschreibung der Rolle – wird als Orientierungs-Banner im Panel gezeigt.
     */
    public function description(): string
    {
        return match ($this) {
            self::WebAdmin => 'System steuern: Rollen, KI-Pipeline, Feedback-Triage und Demo-Daten.',
            self::Geschaeftsfuehrer => 'ROI und Lage im Blick – Abteilungen vergleichen, Entscheidungen vorbereiten.',
            self::Bereichsleiter => 'Eigene Abteilung disponieren: Ausfälle lösen, Vorschläge prüfen, zuweisen.',
            self::Nutzer => 'Eigene Schichten sehen und Verfügbarkeit melden (z. B. krank).',
        };
    }

    /**
     * Alle Rollen dürfen ins Panel – die Sicht unterscheidet sich über
     * Policies, Navigation und Dashboard-Widgets.
     */
    public function canAccessPanel(): bool
    {
        return true;
    }

    /**
     * Rollen, die Schichten aller Abteilungen sehen (Reporting/Sicht).
     */
    public function seesAllDepartments(): bool
    {
        return match ($this) {
            self::WebAdmin, self::Geschaeftsfuehrer => true,
            self::Bereichsleiter, self::Nutzer => false,
        };
    }

    /**
     * Rollen, die Schichten aktiv disponieren dürfen (zuweisen/rollback).
     */
    public function canDispatch(): bool
    {
        return match ($this) {
            self::WebAdmin, self::Bereichsleiter => true,
            self::Geschaeftsfuehrer, self::Nutzer => false,
        };
    }

    /**
     * Rollen mit Zugriff auf System-Einstellungen (Pipeline, Feedback-Toggle).
     */
    public function managesSettings(): bool
    {
        return $this === self::WebAdmin;
    }

    /**
     * Rollen mit Zugriff auf ROI-/Reporting-Kennzahlen.
     */
    public function seesMetrics(): bool
    {
        return $this->canDispatch() || $this->seesAllDepartments();
    }
}

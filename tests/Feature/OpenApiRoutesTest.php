<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Ogni percorso annotato deve corrispondere a una rotta registrata (punto TOA09).
 *
 * IN UNA DIREZIONE SOLA, ed e' cio' che rende il controllo utilizzabile.
 *
 * «Ogni annotazione ha una rotta» e' un invariante: un'annotazione che punta a un percorso
 * inesistente e' documentazione che mente, e succede appena qualcuno rinomina una rotta senza
 * toccare l'attributo — i due posti non si parlano.
 *
 * «Ogni rotta e' documentata» NON lo e': gli stessi controller servono anche le rotte interne
 * `admin/v1/…` usate dall'interfaccia, e alcune rotte `api/v1` non sono documentate di proposito.
 * Controllare anche quello segnalerebbe il corretto, e un controllo che grida sul corretto si
 * smette di leggere.
 *
 * Il confronto e' fra due elenchi di stringhe: non guarda i controller, quindi il fatto che siano
 * condivisi fra web e API non lo disturba.
 */
class OpenApiRoutesTest extends TestCase
{
    /** Lo specifico generato: e' l'elenco autorevole di cio' che il progetto DOCUMENTA. */
    private const SPEC = "api-docs/api-docs.json";

    /**
     * I percorsi documentati, letti dallo specifico generato e non dal sorgente.
     *
     * Prima li estraevo con una regex sui `path: "…"` degli attributi. Ha smesso di funzionare
     * appena quei literali sono diventati costanti (`path: self::OA_PATH . "/{id}"`), e il
     * controllo sarebbe diventato «zero percorsi, tutto a posto» — verde e cieco. Lo specifico
     * generato porta i percorsi gia' risolti, e per giunta e' quello che i client leggono davvero.
     */
    private function documentedPaths(): array
    {
        $file = storage_path(self::SPEC);

        if (!file_exists($file)) {
            $this->fail("Specifico OpenAPI assente: eseguire ./scripts/openapi-spec-diff.sh salva");
        }

        $spec = json_decode(file_get_contents($file), true);

        return array_map(fn($path) => ltrim($path, "/"), array_keys($spec["paths"] ?? []));
    }

    /** Le URI registrate da Laravel. */
    private function registeredUris(): array
    {
        return collect(Route::getRoutes())
            ->map(fn($route) => $route->uri())
            ->unique()
            ->all();
    }

    public function test_every_annotated_path_has_a_route(): void
    {
        $registrate = $this->registeredUris();

        $orfani = array_values(array_filter($this->documentedPaths(), fn($path) => !in_array($path, $registrate, true)));

        $this->assertSame(
            [],
            $orfani,
            "percorsi documentati senza una rotta corrispondente — la documentazione punta nel vuoto:\n  " .
                implode("\n  ", $orfani),
        );
    }

    public function test_the_check_has_something_to_check(): void
    {
        // Un controllo che non trova niente da confrontare passerebbe sempre: e' successo in questo
        // progetto con una regex che non trovava mai niente. Qui si fissa il minimo.
        $this->assertGreaterThanOrEqual(
            8,
            count($this->documentedPaths()),
            "meno percorsi documentati del previsto: il controllo sta guardando nel posto sbagliato",
        );
    }
}

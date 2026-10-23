<?php
declare(strict_types=1);
namespace App\Services;
use App\Contracts\FacturadorInterface;
use DomainException;

/** Registro extensible: agregar una nueva estrategia sin modificar ServicioCobros. */
final class CatalogoFacturadores {
    /** @var array<string,FacturadorInterface> */
    private array $facturadores = [];

    public function __construct(FacturadorInterface ...$facturadores) {
        foreach ($facturadores as $facturador) {
            $this->facturadores[$facturador->tipo()] = $facturador;
        }
    }
    public function seleccionar(string $tipo): FacturadorInterface {
        return $this->facturadores[$tipo]
            ?? throw new DomainException('Tipo de facturación inválido.');
    }
}

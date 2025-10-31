<?php

namespace App\Services\Geo;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CostaRicaLocationMapper
{
    private const COUNTRY_ISO2 = 'CR';

    private const PROVINCES = [
        '1' => [
            'name' => 'San José',
            'state_names' => ['San José Province', 'San Jose Province'],
            'cantons' => [
                '01' => 'San José',
                '02' => 'Escazú',
                '03' => 'Desamparados',
                '04' => 'Puriscal',
                '05' => 'Tarrazú',
                '06' => 'Aserrí',
                '07' => 'Mora',
                '08' => 'Goicoechea',
                '09' => 'Santa Ana',
                '10' => 'Alajuelita',
                '11' => 'Vásquez de Coronado',
                '12' => 'Acosta',
                '13' => 'Tibás',
                '14' => 'Moravia',
                '15' => 'Montes de Oca',
                '16' => 'Turrubares',
                '17' => 'Dota',
                '18' => 'Curridabat',
                '19' => 'Pérez Zeledón',
                '20' => 'León Cortés',
            ],
        ],
        '2' => [
            'name' => 'Alajuela',
            'state_names' => ['Alajuela Province'],
            'cantons' => [
                '01' => 'Alajuela',
                '02' => 'San Ramón',
                '03' => 'Grecia',
                '04' => 'San Mateo',
                '05' => 'Atenas',
                '06' => 'Naranjo',
                '07' => 'Palmares',
                '08' => 'Poás',
                '09' => 'Orotina',
                '10' => 'San Carlos',
                '11' => 'Zarcero',
                '12' => 'Valverde Vega',
                '13' => 'Upala',
                '14' => 'Los Chiles',
                '15' => 'Guatuso',
                '16' => 'Río Cuarto',
            ],
        ],
        '3' => [
            'name' => 'Cartago',
            'state_names' => ['Cartago Province'],
            'cantons' => [
                '01' => 'Cartago',
                '02' => 'Paraíso',
                '03' => 'La Unión',
                '04' => 'Jiménez',
                '05' => 'Turrialba',
                '06' => 'Alvarado',
                '07' => 'Oreamuno',
                '08' => 'El Guarco',
            ],
        ],
        '4' => [
            'name' => 'Heredia',
            'state_names' => ['Heredia Province'],
            'cantons' => [
                '01' => 'Heredia',
                '02' => 'Barva',
                '03' => 'Santo Domingo',
                '04' => 'Santa Bárbara',
                '05' => 'San Rafael',
                '06' => 'San Isidro',
                '07' => 'Belén',
                '08' => 'Flores',
                '09' => 'San Pablo',
                '10' => 'Sarapiquí',
            ],
        ],
        '5' => [
            'name' => 'Guanacaste',
            'state_names' => ['Guanacaste Province'],
            'cantons' => [
                '01' => 'Liberia',
                '02' => 'Nicoya',
                '03' => 'Santa Cruz',
                '04' => 'Bagaces',
                '05' => 'Carrillo',
                '06' => 'Cañas',
                '07' => 'Abangares',
                '08' => 'Tilarán',
                '09' => 'Nandayure',
                '10' => 'La Cruz',
                '11' => 'Hojancha',
            ],
        ],
        '6' => [
            'name' => 'Puntarenas',
            'state_names' => ['Puntarenas Province'],
            'cantons' => [
                '01' => 'Puntarenas',
                '02' => 'Esparza',
                '03' => 'Buenos Aires',
                '04' => 'Montes de Oro',
                '05' => 'Osa',
                '06' => 'Quepos',
                '07' => 'Golfito',
                '08' => 'Coto Brus',
                '09' => 'Parrita',
                '10' => 'Corredores',
                '11' => 'Garabito',
            ],
        ],
        '7' => [
            'name' => 'Limón',
            'state_names' => ['Limón Province', 'Limon Province'],
            'cantons' => [
                '01' => 'Limón',
                '02' => 'Pococí',
                '03' => 'Siquirres',
                '04' => 'Talamanca',
                '05' => 'Matina',
                '06' => 'Guácimo',
            ],
        ],
    ];

    protected ?int $countryId = null;

    /** @var array<int, Collection<int, State>> */
    protected array $statesCache = [];

    /** @var array<int, Collection<int, City>> */
    protected array $citiesCache = [];

    public function resolve(array $location): ?array
    {
        $provinceCode = $this->normalizeProvinceCode($location['province_code'] ?? null);
        $cantonCode = $this->normalizeCantonOrDistrictCode($location['canton_code'] ?? null);

        if (!$provinceCode || !$cantonCode) {
            return null;
        }

        if (!isset(self::PROVINCES[$provinceCode])) {
            Log::warning('Código de provincia de Costa Rica no reconocido.', [
                'province_code' => $provinceCode,
                'location' => $location,
            ]);

            return null;
        }

        $countryId = $this->countryId();

        if (!$countryId) {
            Log::warning('No se encontró el país Costa Rica en la base de datos.');

            return null;
        }

        $provinceData = self::PROVINCES[$provinceCode];
        $state = $this->findState($countryId, $provinceData);

        if (!$state) {
            Log::warning('No se encontró la provincia en la tabla states.', [
                'country_id' => $countryId,
                'province' => $provinceData['name'],
            ]);

            return null;
        }

        $cantons = $provinceData['cantons'] ?? [];

        if (!isset($cantons[$cantonCode])) {
            Log::warning('Código de cantón de Costa Rica no reconocido.', [
                'province_code' => $provinceCode,
                'canton_code' => $cantonCode,
            ]);

            return null;
        }

        $cityName = $cantons[$cantonCode];
        $city = $this->findCity($state->id, $cityName);

        if (!$city) {
            Log::warning('No se encontró el cantón en la tabla cities.', [
                'state_id' => $state->id,
                'city' => $cityName,
            ]);

            return null;
        }

        return [
            'country_id' => $countryId,
            'state_id' => $state->id,
            'city_id' => $city->id,
            'state_name' => $state->name,
            'city_name' => $city->name,
        ];
    }

    protected function countryId(): ?int
    {
        if ($this->countryId !== null) {
            return $this->countryId;
        }

        $country = Country::query()->where('iso2', self::COUNTRY_ISO2)->first();

        $this->countryId = $country?->id;

        return $this->countryId;
    }

    protected function findState(int $countryId, array $provinceData): ?State
    {
        $states = $this->statesForCountry($countryId);
        $candidates = collect(array_merge(
            [$provinceData['name']],
            $provinceData['state_names'] ?? []
        ))
            ->map(fn ($value) => $this->normalizeName($value))
            ->unique();

        return $states->first(function (State $state) use ($candidates) {
            $normalizedState = $this->normalizeName($state->name);

            return $candidates->contains($normalizedState);
        });
    }

    protected function findCity(int $stateId, string $name): ?City
    {
        $cities = $this->citiesForState($stateId);
        $normalizedTarget = $this->normalizeName($name);

        return $cities->first(function (City $city) use ($normalizedTarget) {
            return $this->normalizeName($city->name) === $normalizedTarget;
        });
    }

    protected function statesForCountry(int $countryId): Collection
    {
        return $this->statesCache[$countryId] ??= State::query()
            ->where('country_id', $countryId)
            ->get();
    }

    protected function citiesForState(int $stateId): Collection
    {
        return $this->citiesCache[$stateId] ??= City::query()
            ->where('state_id', $stateId)
            ->get();
    }

    protected function normalizeName(string $value): string
    {
        return strtolower(trim(Str::ascii($value)));
    }

    protected function normalizeProvinceCode(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $value);

        if ($digits === '') {
            return null;
        }

        $number = (int) $digits;

        return $number > 0 ? (string) $number : null;
    }

    protected function normalizeCantonOrDistrictCode(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $value);

        if ($digits === '') {
            return null;
        }

        return str_pad((string) ((int) $digits), 2, '0', STR_PAD_LEFT);
    }
}


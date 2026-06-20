<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    protected $model = Location::class;

    private array $latAmCities = [
        ['city' => 'Bogotá', 'country' => 'Colombia', 'lat' => 4.7110, 'lng' => -74.0721],
        ['city' => 'Medellín', 'country' => 'Colombia', 'lat' => 6.2442, 'lng' => -75.5812],
        ['city' => 'Cali', 'country' => 'Colombia', 'lat' => 3.4516, 'lng' => -76.5319],
        ['city' => 'Barranquilla', 'country' => 'Colombia', 'lat' => 10.9639, 'lng' => -74.7964],
        ['city' => 'Cartagena', 'country' => 'Colombia', 'lat' => 10.3910, 'lng' => -75.4794],
        ['city' => 'Bucaramanga', 'country' => 'Colombia', 'lat' => 7.1193, 'lng' => -73.1227],
        ['city' => 'Pereira', 'country' => 'Colombia', 'lat' => 4.8087, 'lng' => -75.6906],
        ['city' => 'Manizales', 'country' => 'Colombia', 'lat' => 5.0689, 'lng' => -75.5174],
        ['city' => 'Cúcuta', 'country' => 'Colombia', 'lat' => 7.8939, 'lng' => -72.5078],
        ['city' => 'Ibagué', 'country' => 'Colombia', 'lat' => 4.4447, 'lng' => -75.2424],
        ['city' => 'Villavicencio', 'country' => 'Colombia', 'lat' => 4.1420, 'lng' => -73.6266],
        ['city' => 'Pasto', 'country' => 'Colombia', 'lat' => 1.2059, 'lng' => -77.2858],
        ['city' => 'Armenia', 'country' => 'Colombia', 'lat' => 4.5350, 'lng' => -75.6757],
        ['city' => 'Neiva', 'country' => 'Colombia', 'lat' => 2.9345, 'lng' => -75.2809],
        ['city' => 'Soledad', 'country' => 'Colombia', 'lat' => 10.9154, 'lng' => -74.7819],
        ['city' => 'Montería', 'country' => 'Colombia', 'lat' => 8.7479, 'lng' => -75.8874],
        ['city' => 'Valledupar', 'country' => 'Colombia', 'lat' => 10.4631, 'lng' => -73.2532],
        ['city' => 'Sincelejo', 'country' => 'Colombia', 'lat' => 9.3046, 'lng' => -75.3908],
        ['city' => 'Riohacha', 'country' => 'Colombia', 'lat' => 11.5444, 'lng' => -72.9079],
        ['city' => 'Popayán', 'country' => 'Colombia', 'lat' => 2.4382, 'lng' => -76.6132],
        ['city' => 'Florencia', 'country' => 'Colombia', 'lat' => 1.6144, 'lng' => -75.6062],
        ['city' => 'Quibdó', 'country' => 'Colombia', 'lat' => 5.6947, 'lng' => -76.6611],
        ['city' => 'San Andrés', 'country' => 'Colombia', 'lat' => 12.5833, 'lng' => -81.7006],
        ['city' => 'Leticia', 'country' => 'Colombia', 'lat' => -4.2153, 'lng' => -69.9403],
        ['city' => 'Inírida', 'country' => 'Colombia', 'lat' => 3.8683, 'lng' => -67.9239],
        ['city' => 'Mocoa', 'country' => 'Colombia', 'lat' => 1.1493, 'lng' => -76.6469],
        ['city' => 'Yopal', 'country' => 'Colombia', 'lat' => 5.3386, 'lng' => -72.3956],
        ['city' => 'Tunja', 'country' => 'Colombia', 'lat' => 5.5353, 'lng' => -73.3678],
        ['city' => 'Girardot', 'country' => 'Colombia', 'lat' => 4.3024, 'lng' => -74.8030],
        ['city' => 'Fusagasugá', 'country' => 'Colombia', 'lat' => 4.3368, 'lng' => -74.3638],
        ['city' => 'Mexico City', 'country' => 'Mexico', 'lat' => 19.4326, 'lng' => -99.1332],
        ['city' => 'Guadalajara', 'country' => 'Mexico', 'lat' => 20.6597, 'lng' => -103.3496],
        ['city' => 'Monterrey', 'country' => 'Mexico', 'lat' => 25.6866, 'lng' => -100.3161],
        ['city' => 'Lima', 'country' => 'Peru', 'lat' => -12.0464, 'lng' => -77.0428],
        ['city' => 'Santiago', 'country' => 'Chile', 'lat' => -33.4489, 'lng' => -70.6693],
        ['city' => 'Buenos Aires', 'country' => 'Argentina', 'lat' => -34.6037, 'lng' => -58.3816],
        ['city' => 'São Paulo', 'country' => 'Brazil', 'lat' => -23.5505, 'lng' => -46.6333],
        ['city' => 'Rio de Janeiro', 'country' => 'Brazil', 'lat' => -22.9068, 'lng' => -43.1729],
        ['city' => 'Quito', 'country' => 'Ecuador', 'lat' => -0.1807, 'lng' => -78.4678],
        ['city' => 'Caracas', 'country' => 'Venezuela', 'lat' => 10.4806, 'lng' => -66.9036],
        ['city' => 'La Paz', 'country' => 'Bolivia', 'lat' => -16.5000, 'lng' => -68.1500],
        ['city' => 'Asunción', 'country' => 'Paraguay', 'lat' => -25.2637, 'lng' => -57.5759],
        ['city' => 'Montevideo', 'country' => 'Uruguay', 'lat' => -34.9011, 'lng' => -56.1645],
        ['city' => 'San José', 'country' => 'Costa Rica', 'lat' => 9.9281, 'lng' => -84.0907],
        ['city' => 'Panama City', 'country' => 'Panama', 'lat' => 8.9824, 'lng' => -79.5199],
        ['city' => 'Guatemala City', 'country' => 'Guatemala', 'lat' => 14.6349, 'lng' => -90.5069],
        ['city' => 'San Salvador', 'country' => 'El Salvador', 'lat' => 13.6929, 'lng' => -89.2182],
        ['city' => 'Tegucigalpa', 'country' => 'Honduras', 'lat' => 14.0723, 'lng' => -87.1921],
        ['city' => 'Managua', 'country' => 'Nicaragua', 'lat' => 12.1149, 'lng' => -86.2362],
        ['city' => 'San Juan', 'country' => 'Puerto Rico', 'lat' => 18.4655, 'lng' => -66.1057],
        ['city' => 'Havana', 'country' => 'Cuba', 'lat' => 23.1136, 'lng' => -82.3666],
        ['city' => 'Santo Domingo', 'country' => 'Dominican Republic', 'lat' => 18.4861, 'lng' => -69.9312],
    ];

    public function definition(): array
    {
        $cityData = fake()->randomElement($this->latAmCities);
        $streetTypes = ['Calle', 'Carrera', 'Avenida', 'Diagonal', 'Transversal', 'Autopista'];
        $streetType = fake()->randomElement($streetTypes);
        $streetNumber = fake()->numberBetween(1, 150);
        $houseNumber = fake()->numberBetween(1, 100);
        $suffixes = ['', 'A', 'B', 'C', 'Sur', 'Norte', 'Este', 'Oeste'];
        $suffix = fake()->randomElement($suffixes);
        $neighborhoods = [
            'Centro', 'El Poblado', 'Chapinero', 'Miraflores', 'Polanco', 'Ipanema',
            'San Isidro', 'La Candelaria', 'El Cabanyal', 'Palermo', 'Recoleta', 'Providencia',
            'Condado', 'Zona Rosa', 'Galerías', 'Laureles', 'Versalles', 'Nueva Cordoba',
            'Las Condes', 'San Borja', 'Barranco', 'El Vedado', 'González Suárez', 'Rosedal',
        ];

        return [
            'name' => 'Sede ' . fake()->company() . ' - ' . $cityData['city'],
            'address' => $streetType . ' ' . $streetNumber . $suffix . ' #' . $houseNumber . '-' . fake()->numberBetween(1, 99) . ', ' . fake()->randomElement($neighborhoods),
            'city' => $cityData['city'],
            'country' => $cityData['country'],
            'latitude' => $cityData['lat'] + fake()->randomFloat(6, -0.05, 0.05),
            'longitude' => $cityData['lng'] + fake()->randomFloat(6, -0.05, 0.05),
            'contact_name' => fake()->name(),
            'phone' => fake()->numerify('+57 3## ### ####'),
        ];
    }
}

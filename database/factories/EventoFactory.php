<?php

namespace Database\Factories;

use App\Models\Evento;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Faker\Generator as Faker;
/**
 * @extends Factory<Eventos>
 */
class EventoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    
    public function definition(): array
    {
       // $modalidad=fake()->randomElement(['presencial','virtual','hibrido']);
    //    $checkin_tipo=$this->faker->randomElement(['kit', 'acreditacion', 'ambos']);
	    //$tiene_delivery=$this->faker->randomElement(['0', '1']);
	    //$tiene_punto_venta=$this->faker->randomElement(['0', '1']);
	  //  $tiene_desafios=$this->faker->randomElement(['0', '1']);
	   // $publicado=$this->faker->randomElement(['0', '1']);
	   // $destacado=$this->faker->randomElement(['0', '1']);
      //  $hasDonation=$this->faker->randomElement(['0', '1']);
	    //$contador_visitas=$this->faker->randomElement(['0', '1']);
        $status=['open', 'closed', 'coming_soon'];

        $youtubeId = Str::random(11); 
        return [
            'organizador_id'=> fake()->unique(true)->numberBetween($min = 1, $max = 999),
            'tipo_evento_id'=> fake()->unique(true)->numberBetween($min = 1, $max = 999),
            'subtipo_evento_id'=>fake()->unique(true)->numberBetween($min = 1, $max = 999),
            'estado_evento_id'=> fake()->randomElement($status),
            'pais_id'=> fake()->unique(true)->numberBetween($min = 1, $max = 999),
            'ciudad_id'=> fake()->unique(true)->numberBetween($min = 1, $max = 999),
            'nombre'=> fake()->name(),
            'nombre_corto'=> fake()->name(),
            'url_slug'=>  fake()->url(),
            'keyword'=> fake()->name(),
            'descripcion' => fake()->paragraph(),
            'longDescription' => fake()->paragraph(),
            'reglamento'=> fake()->paragraph(),
            'deslinde'=> fake()->paragraph(),
            'fecha_inicio'=> fake()->dateTime(),
            'fecha_fin'=> fake()->dateTime(),
            'fecha_apertura_inscrip'=> fake()->dateTime(),
            'fecha_cierre_inscrip'=> fake()->dateTime(),
            'mensaje_cierre'=> fake()->paragraph(),
            'lugar' =>fake()->paragraph(),
            'direccion'=>fake()->streetName(),
            'modalidad'=> fake()->randomElement(['presencial','virtual','hibrido']),
            'url_virtual'=> fake()->url(),
            'aforo_total'=>fake()->numberBetween(1, 100),
            'color_id'=>fake()->unique(true)->randomDigitNotNull(),
            'logo_url'=> $this->faker->imageUrl(640, 480, 'sports', true), 
            'imagen_portada_url'=>  fake()->url(), 
            'icono_url'=>  fake()->url(), 
            'video_url'=> $youtubeId , 
            'gpx_url'=>  fake()->url(), 
        //    'coordinates' => $this->faker->latitude(),  // Generates random float between -90 and 90
        //    'route' => $this->faker->longitude(), // Generates random float between -180 and 180
       // 'formTypes' => \App\Models\FormType::factory()->count(3), // Generate 3 form types for each event
            'link_strava'=>  fake()->url(),
            'checkin_tipo'=>fake()->randomElement(['kit', 'acreditacion', 'ambos']), // $checkin_tipo=$this->faker->randomElement(['kit', 'acreditacion', 'ambos']),
            'tiene_delivery'=>fake()->randomElement(['0', '1']), // $tiene_delivery=$this->faker->randomElement(['0', '1']),
            'tiene_punto_venta'=>fake()->randomElement(['0', '1']), // $tiene_punto_venta=$this->faker->randomElement(['0', '1'])
            'tiene_desafios'=>fake()->randomElement(['0', '1']), // $tiene_desafios=$this->faker->randomElement(['0', '1'])
            'publicado'=>fake()->randomElement(['0', '1']), // $publicado=$this->faker->randomElement(['0', '1'])
            'destacado'=>fake()->randomElement(['0', '1']), // $destacado=$this->faker->randomElement(['0', '1'])
            'hasDonation'=> $this->faker->boolean(), // $hasDonation=$this->faker->randomElement(['0', '1'])
            'contador_visitas'=>fake()->randomElement(['0', '1']), // $contador_visitas=$this->faker->randomElement(['0', '1'])
        ];
    }
}

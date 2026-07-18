# Cambios: Cambios en el Registro de Evento con Relaciones Anidadas
hemos agregado y modificado algunas tablas y modelos
FormType
FormularioCampos
QuestionOptions

#migraciones modificadas
2026_07_02_121800_create_form_types_table
2026_07_18_020614_create_formulario_campos_table
2026_07_18_055122_create_question_options_table



La idea es que ahora necesitamos incluir preguntas y opciones de preguntas en la bd al momento de registrar un evento
tambien se necesita crear los seeders para esas tres tablas para las pruebas 
modificar los get de event para incluir preguntas y opciones de preguntas






## Ejemplo de request

```json
POST /api/v1/event
Content-Type: application/json

{
  "name": "Carrera por la Vida 2025",
  "description": "Carrera benéfica de 5K y 10K",
  "longDescription": "Evento deportivo anual",
  "date": "2025-07-26 11:47:05",
  "localTime": "13:22:56",
  "location": "Santa Cruz de la Sierra",
  "hasDonation": false,
  "video": "eq4GIhnPFrs",
  "image": "https://example.com/portada.jpg",
  "coordinates": [
    { "lat": -17.7833, "lng": -63.1821 }
  ],
  "route": [
    { "lat": -17.7833, "lng": -63.1821, "label": "Punto de salida" },
    { "lat": -17.7900, "lng": -63.1900, "label": "Km 5" }
  ],
  "categories": [
    { "name": "5K", "price": 100, "description": "Carrera corta", "color": "#ff0000" },
    { "name": "10K", "price": 150, "description": "Carrera larga", "color": "#0000ff" }
  ],
  "formTypes": [
    {
      "name": "General",
      "icon": "🏃",
      "description": "Inscripcion general",
      "tipo": "deportivo",
      "cupo_total": 500,
      "precio_base": 100,
      "color": "#00ff00",
      "moneda": 1,
      "permite_lista_espera": 1,
      "hasshirt": 1,
      "requiere_talla": 1,
      "souvenirs": [
        { "name": "Remera", "icon": "👕", "price": 25 },
        { "name": "Medalla", "icon": "🏅", "price": 10 }
      ],
	  "preguntas": [
			{ 
			"form_types_id ": 1,
			"nombre_campo": "Remera",
			"etiqueta": "Remera",
			"placeholder": "Remera", 
			"obligatorio": true, 
			"tipo_input": "text", 
			"orden": 1 
			},
			{ 
			"form_types_id ": 1,
			"nombre_campo": "Remera",
			"etiqueta": "Remera",
			"placeholder": "Remera",
			"obligatorio":false,
			"tipo_input": "radio", 
			"orden": 2
			"options": [
			 {"question_id" :1,"option_text" :"Masculino","order" :1},
			 {"question_id" :2,"option_text" :"Femenino","order" :2}
			]
			}
	  ]
    }
  ],
  "promoCodes": [
    { "promo_code": "EARLY50", "price": 50 }
  ]
}


## Ejemplo de respuesta (201)

```json
{
  "success": true,
  "message": "Evento registrado correctamente.",
  "eventos": {
    "id": 1,
    "name": "Carrera por la Vida 2025",
    "date": "2025-07-26 11:47:05",
    "coordinates": [{ "lat": -17.7833, "lng": -63.1821 }],
    "route": [{ "lat": -17.7833, "lng": -63.1821, "label": "Punto de salida" }],
    "categories": [
	 {"name": "5K", 
	  "price": 100 
	 }],
    "formTypes": [{
      "name": "General",
      "souvenirs": [
        { "form_types_id": 1, "name": "Remera", "icon": "👕", "price": 25 }
      ],
	  "preguntas": [
			{ 
			"form_types_id ": 1,
			"nombre_campo": "Remera",
			"etiqueta": "Remera",
			"placeholder": "Remera", 
			"obligatorio": true, 
			"tipo_input": "text", 
			"orden": 1 
			},
			{ 
			"form_types_id ": 1,
			"nombre_campo": "Remera",
			"etiqueta": "Remera",
			"placeholder": "Remera",
			"obligatorio":false,
			"tipo_input": "radio", 
			"orden": 2
			"options": [
			 {"question_id" :1,"option_text" :"Masculino","order" :1},
			 {"question_id" :2,"option_text" :"Femenino","order" :2}
			]
			}
	  ]
    }],
    "promoCodes": [{ "promo_code": "EARLY50", "price": 50 }]
  }
}
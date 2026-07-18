Necesitamos  registar el identificador del formulario al momento de registrar una inscripcion 
luego tambien necesitamos registrar 'answers' que son respuestas del participante en preguntas adicionales 

se sabe que existen preguntas adicionales cuando en form_types el campo es hasQuestion=1

revisar los modelos
Answer
FormType
FormularioCampos
Participante
QuestionOptions

revisar los controladores
EventoController

revisar los servicios
RegistrationService

revisar DTO
FormTypeDTO
RegistrationDTO

revisar las migraciones y relaciones

2026_07_03_123431_create_participantes_table
2026_07_02_121800_create_form_types_table
2026_07_18_020614_create_formulario_campos_table
2026_07_18_055122_create_question_options_table
2026_07_18_125734_create_answers_table


answer puede ser vacio []


ANTES el input era asi:

[
    {
        "referencia": "LA-9167800F",
        "fecha": "2026-07-05 14:34:11",
        "evento_id": "1",
        "evento_nombre": "Margret Schultz Sr.",
        "tipo_pago": "QR",
        "pago_status": "pending",
        "totales": {
            "inscripcion": 5.82,
            "donacion": 50,
            "souvenirs": 69.6,
            "fee": 0.29,
            "descuento": 0,
            "grand_total": 125.71
        },
        "participantes": [
            {
                "nombre": "Gerd",
                "apellido": "Claros",
                "alias": "g",
                "genero": "Masculino",
                "tipoDocumento": "DNI",
                "numeroDocumento": "962633",
                "polera": "No shirt",
                "precioPolera": 0,
                "nacimiento": {
                    "dia": "8",
                    "mes": "8",
                    "anio": "1985"
                },
                "edad": 40,
                "correo": "carlitos.gerd@gmail.com",
                "direccion": "AV Dòrbigny, Depto 333",
                "ciudad": "Cochabamba",
                "telefono": "+59178441410",
                "contacto_emergencia": {
                    "nombre": "Gerd Claros",
                    "celular": "+59178441410",
                    "relacion": "LGN"
                },
                "donacion": 50,
                "souvenirs": [
                    {
                        "id": "1",
                        "nombre": "omnis",
                        "precio": 69.6
                    }
                ],
                "categoria": "1",
                "precioCategoria": 5.82,
                "promoDescuento": 0,
                "promoCodigo": "",
                "subtotal": 125.42
            }
        ]
    }
]


AHORA

[
    {
        "referencia": "LA-9167800F",
        "fecha": "2026-07-05 14:34:11",
        "evento_id": "1",
        "form_types_id": "1",
        "evento_nombre": "Margret Schultz Sr.",
        "tipo_pago": "QR",
        "pago_status": "pending",
        "totales": {
            "inscripcion": 5.82,
            "donacion": 50,
            "souvenirs": 69.6,
            "fee": 0.29,
            "descuento": 0,
            "grand_total": 125.71
        },
        "participantes": [
            {
                "nombre": "Gerd",
                "apellido": "Claros",
                "alias": "g",
                "genero": "Masculino",
                "tipoDocumento": "DNI",
                "numeroDocumento": "962633",
                "polera": "No shirt",
                "precioPolera": 0,
                "nacimiento": {
                    "dia": "8",
                    "mes": "8",
                    "anio": "1985"
                },
                "edad": 40,
                "correo": "carlitos.gerd@gmail.com",
                "direccion": "AV Dòrbigny, Depto 333",
                "ciudad": "Cochabamba",
                "telefono": "+59178441410",
                "contacto_emergencia": {
                    "nombre": "Gerd Claros",
                    "celular": "+59178441410",
                    "relacion": "LGN"
                },
                "donacion": 50,
                "souvenirs": [
                    {
                        "id": "1",
                        "nombre": "omnis",
                        "precio": 50
                    }
                ],
                "answers": [
                    {
                        "form_types_id": 1,
                        "question_id": 1,
						"participante_id": 1,
                        "value": "BLABLA"
                    }
                ],
                "categoria": "1",
                "precioCategoria": 5.82,
                "promoDescuento": 0,
                "promoCodigo": "",
                "subtotal": 125.42
            }
        ]
    }
]

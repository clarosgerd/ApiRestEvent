# Filtro de Eventos por Categoría

## Descripción

Agregar la capacidad de filtrar eventos antes de la lista de eventos colocar un buscador de eventos colocar un lupa talvez

El buscador usara los endpoint
GET  http://127.0.0.1:8000/api/v1/event?nombre[eq]=Candelario Schroeder
GET  http://127.0.0.1:8000/api/v1/event?nombre[li]=Candelario 

Podemos usar tambien en la busqueda de eventos con sus categorias palabras claves
GET http://127.0.0.1:8000/api/v1/event?category[eq]=3k
Devuelve todos los eventos que tengan una categoría con el nombre exacto "3k".

GET  http://127.0.0.1:8000/api/v1/event?category[li]='3k'
Devuelve todos los eventos que tengan una categoría cuyo nombre contenga "3k" (ej: "3k", "3km", "Categoría 3k").

### Combinado con otros filtros

GET /api/v1/event?category[eq]=3k&publicado[eq]=1
Devuelve todos los eventos publicados que tengan la categoría "3k".



Despues del buscador de eventos colocar un boton o un icono de busquador avanzado que habilite un conjunto de campos como ser
-un combo (open,coming_soo,closed)
-un combo o campo de texto de ubicacion del lugar
-un combo por tipo de evento 'deportivo','congreso','taller','corporativo','cultural','social','educativo','recreativo','religioso','gastronomico','musical','tecnologico','artes','literario','ambiental','salud','moda','teatro','cine','fotografia','danza','literatura'
-un rango de por precios de categorias
-un rango por fecha basandonos en la fecha de inicio de evento


basandonos en los datos que se encuentran en el archivo .env requerimos usar la informacion para realizar el pago por QR

el qr debe generarse en base64



/**** simulacion 
BASE_AUTH_URL=http://127.0.0.1:800/api/v1/autenticacion/
BASE_API_URL=http://127.0.0.1:800/api/v1/
USERNAME=GERD
PASSWORD=$ecret2026
APIKEY_TEST=de1ffc35c158f30e1f5dfba79f5ef47ba7885c932098668f                # usado solo para generarToken
APIKEY_SERVICIO=5aa3b42bba5e8ae13017605bc7bbdd7f0a8fc513a37ed4bb # usado para generaQr / estadoTransaccion / inhabilitarPago
VERIFY_SS_TEST=true      

basandonos como datos de entrada USERNAME y PASSWORD generamos un token

http://127.0.0.1:800/api/v1/registrations/{reference}/generarToken
{
    "username": "USERNAME",  //DATOS DEL ENV
    "password": "PASSWORD"
}


usando el token obtenemos el estado del registro 

http://127.0.0.1:800/api/v1/registrations/{reference}/estadoTransaccion
{
  "alias": "pending"
}

Basandonos en el datos de entrada y el estado de registro pending y el token generamos un QR

http://127.0.0.1:800/api/v1/registrations/{reference}/generaQr

{
  "referencia": "Prueba123102",  ////referencia
  "callback": "000",
  "detalleGlosa": "pagoTest261120201", //evento_nombre
  "monto": "1.0",/// grand_total de la tabla registration_totals
  "moneda": "BOB",
  "fechaVencimiento": "30/12/2024",/////fecha 
  "tipoSolicitud": "API"
}

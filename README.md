# 📊 Factheo — Sistema de Facturación Electrónica Ecuador

> **Factheo** es un sistema web de facturación electrónica diseñado para cumplir con la normativa del Servicio de Rentas Internas (SRI) del Ecuador. Genera, firma y autoriza comprobantes electrónicos bajo el esquema **Offline** del SRI.

---

## 📋 Tabla de Contenidos

1. [Características Principales](#características-principales)
2. [Requisitos del Sistema](#requisitos-del-sistema)
3. [Instalación](#instalación)
4. [Configuración](#configuración)
   - [Base de Datos](#base-de-datos)
   - [Conexión a Internet](#conexión-a-internet)
   - [Certificado de Firma Electrónica](#certificado-de-firma-electrónica)
5. [Arquitectura del Sistema](#arquitectura-del-sistema)
6. [Módulos](#módulos)
7. [Flujo de Autorización SRI](#flujo-de-autorización-sri)
   - [Generación XML](#1-generación-xml)
   - [Firma Electrónica (XAdES_BES)](#2-firma-electrónica-xades_bes)
   - [Envío al SRI (WS Recepción)](#3-envío-al-sri-ws-recepción)
   - [Consulta de Autorización (WS Autorización)](#4-consulta-de-autorización-ws-autorización)
   - [Generación RIDE (PDF)](#5-generación-ride-pdf)
8. [Web Services SRI](#web-services-sri)
   - [Ambientes](#ambientes)
   - [Endpoints](#endpoints)
9. [Comprobantes Electrónicos Soportados](#comprobantes-electrónicos-soportados)
10. [Base de Datos](#base-de-datos-1)
11. [Estructura del Proyecto](#estructura-del-proyecto)
12. [Mantenimiento](#mantenimiento)
13. [Despliegue](#despliegue)
14. [Licencia](#licencia)

---

## 🚀 Características Principales

- ✅ **Facturación electrónica** v1.1.0 compatible con SRI
- ✅ **Firma digital XAdES_BES** (RSA-SHA1, 2048 bits) usando QuijoteLuiFirmador (Java)
- ✅ **Notas de Crédito y Débito** electrónicas
- ✅ **Guías de Remisión** electrónicas
- ✅ **Liquidaciones de Compra** de bienes y prestación de servicios
- ✅ **Comprobantes de Retención** electrónicos
- ✅ **Múltiples empresas** desde un solo panel
- ✅ **Puntos de emisión** ilimitados
- ✅ **Gestión de clientes, productos, bodegas y proveedores**
- ✅ **Dashboard** con indicadores clave (ventas, IVA, ICE)
- ✅ **Ambientes de Pruebas y Producción** SRI
- ✅ **RIDE** (Representación Impresa) en PDF vía TCPDF
- ✅ **Suscripciones** y control de acceso multi-usuario
- ✅ **Décimo Tercero** y otras plantillas automáticas
- ✅ **Envío automático** de comprobantes por email
- ✅ **Soporte IVA 15%** (tarifa vigente), ICE, IRBPNR
- ✅ **Régimen RIMPE** (Negocio Popular y Emprendedor)
- ✅ **Exportación** y **Reembolso** de gastos
- ✅ **IVA diferenciado** (turismo, materiales construcción)
- ✅ **Autoretenciones** (combustibles, periódicos)

---

## 💻 Requisitos del Sistema

| Componente | Versión Mínima | Recomendada |
|-----------|---------------|-------------|
| **PHP** | 8.0 | **8.1.2+** |
| **MySQL / MariaDB** | 5.7 | **10.5+** |
| **Java (JRE)** | 8 | **11+** (para firmador) |
| **Web Server** | Apache 2.4 | **Apache 2.4** |
| **SSL** | Requerido en producción | Let's Encrypt |
| **Conexión Internet** | 256 Kbps | 1 Mbps+ (WS SRI) |
| **Sistema Operativo** | Linux/Windows | **Linux (Debian/Kali/Ubuntu)** |
| **Extensiones PHP** | PDO, MySQL, cURL, DOM, OpenSSL, MBString, GD | |
| **Memoria PHP (upload)** | 32 MB | 128 MB+ |

---

## 🔧 Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/SoyFenixEc/factheo_php.git
cd factheo_php
```

### 2. Configurar permisos

```bash
chmod -R 755 sistema/md_facturacion/autorizacion/comprobantes
chmod -R 755 sistema/md_empresa/certificados
chmod -R 755 sistema/md_empresa/logos
```

### 3. Configurar base de datos

```bash
mysql -u root -p < factheo.sql
```

O importar manualmente `factheo.sql` desde phpMyAdmin.

### 4. Configurar conexión

Editar `sistema/md_config/conexion.php`:

```php
$host = 'localhost';
$dbname = 'contable';
$username = 'tu_usuario';
$password = 'tu_contraseña';
```

### 5. Verificar Java

```bash
java -version
```

El firmador QuijoteLuiFirmador requiere JRE 8+.

### 6. Acceder al sistema

```
http://localhost/factheo/
```

**Credenciales por defecto:**
- Email: `juan@example.com`
- Clave: `123`

> ⚠️ **Cambiar contraseña inmediatamente en producción**

---

## ⚙️ Configuración

### Base de Datos

El archivo `sistema/md_config/conexion.php` contiene los parámetros de conexión:

| Variable | Descripción |
|----------|-------------|
| `$host` | Servidor MySQL (default: `localhost`) |
| `$dbname` | Nombre BD (default: `contable`) |
| `$username` | Usuario MySQL |
| `$password` | Contraseña MySQL |
| Timezone | `America/Guayaquil` (UTC-5) |

La conexión utiliza **PDO** con charset UTF-8 y fetch mode `FETCH_ASSOC`.

### Conexión a Internet

El sistema requiere conexión a Internet para:
- **Envío de XML** al Web Service de Recepción del SRI
- **Consulta de autorización** al Web Service de Autorización del SRI
- **Envío de correos electrónicos** a clientes
- **Notificaciones** por email de comprobantes autorizados

### Certificado de Firma Electrónica

Los certificados `.p12` (PKCS12) se almacenan en:
```
sistema/md_empresa/certificados/
```

**Requisitos del certificado:**
- Formato: PKCS12 (`.p12`)
- Algoritmo: RSA-SHA1 (2048 bits)
- Estándar de firma: XAdES_BES
- Emitido por entidad certificadora autorizada (ANFAC, Security Data, BCE, etc.)

La configuración del certificado se realiza desde el módulo **Empresa → Datos de la Empresa**, donde se especifica:
- Ruta del archivo `.p12`
- Clave del certificado (contraseña)

---

## 🏗️ Arquitectura del Sistema

```
┌────────────────────────────────────────────────────────────┐
│                     NAVEGADOR WEB                          │
└─────────────────────┬──────────────────────────────────────┘
                      │ HTTP/HTTPS
┌─────────────────────▼──────────────────────────────────────┐
│                   PHP (Apache)                             │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                   Módulos (md_*)                      │  │
│  │  Clientes │ Productos │ Bodegas │ Proveedores │ ...  │  │
│  └──────────────────────────────────────────────────────┘  │
│                     │                                       │
│         ┌───────────┴───────────┐                           │
│         ▼                       ▼                           │
│  ┌──────────┐           ┌──────────────┐                    │
│  │  MySQL   │           │  Java (JAR)  │                    │
│  │  (PDO)   │           │  Firma XML   │                    │
│  └──────────┘           └──────┬───────┘                    │
│                               │                             │
└───────────────────────────────┼─────────────────────────────┘
                                │ XML Firmado
                                ▼
                    ┌───────────────────────┐
                    │   SRI (Web Services)   │
                    │  cel.sri.gob.ec        │
                    │  Recepción + Consulta  │
                    └───────────────────────┘
```

**Modelo de Datos (BD contable):**
```
usuarios → empresa → punto_emision → facturas → detalle_factura
                     │
                     ├── clientes
                     ├── productos
                     ├── bodegas
                     ├── proveedores
                     └── formas_pago
```

---

## 📦 Módulos

| Módulo | Archivos | Descripción |
|--------|----------|-------------|
| **Dashboard** | `md_dashboard/` | Panel principal con KPIs (ventas, IVA, ICE, retenciones). Filtro por empresa y fechas. Gráficos y tablas resumen. |
| **Facturación** | `md_facturacion/` | Módulo central. CRUD de facturas, generación XML v1.1.0, envío WS SRI, autorización, PDF RIDE. |
| **Nota Crédito** | `md_nota_credito/` | Anulación/corrección de facturas. XML v1.1.0, firma, envío SRI, PDF. |
| **Nota Débito** | `md_nota_debito/` | Incremento de valores en facturas emitidas. XML v1.1.0, firma, envío SRI, PDF. |
| **Guía Remisión** | *(en desarrollo)* | Guías de remisión electrónicas. |
| **Comp. Retención** | *(en desarrollo)* | Comprobantes de retención electrónicos. |
| **Liquidación Compra** | `md_liquidacion_compra/` | Liquidaciones de compras. |
| **Clientes** | `md_clientes/` | CRUD clientes. Campos: RUC, cédula, pasaporte, dirección, email, teléfono. |
| **Productos** | `md_productos/` | CRUD productos/servicios. Código principal, auxiliar, precio, impuestos (IVA, ICE, IRBPNR). |
| **Bodegas** | `md_bodegas/` | Gestión de inventario por bodegas. |
| **Proveedores** | `md_proveedores/` | Gestión de proveedores. |
| **Empresa** | `md_empresa/` | Datos de la empresa emisora. Carga de certificado .p12, logo. Gestión multi-empresa. |
| **Punto Emisión** | `md_punto_emision/` | Puntos de emisión (establecimiento + punto). |
| **Formas Pago** | `md_formas_pago/` | Catálogo de formas de pago. |
| **Configuración** | `md_config/` | Ajustes del sistema, conexión BD. |
| **Autenticación** | `md_autenticacion/` | Login, registro, sesión. |
| **Suscripciones** | `md_suscripciones/` | Planes de suscripción y control de acceso. |
| **Registros** | `md_registros/` | Logs de actividad, sesiones activas, geolocalización IP. |

---

## 🔄 Flujo de Autorización SRI

### 1. Generación XML

**Archivo:** `sistema/md_facturacion/facturacion_generar_xml_1_1_0.php`

El sistema genera el XML del comprobante en formato **v1.1.0** del SRI:

- **Clave de acceso** de 49 dígitos (fecha + tipo + RUC + ambiente + serie + secuencial + código numérico + tipo emisión + dígito verificador)
- Datos del emisor (infoTributaria)
- Datos del receptor (infoFactura)
- Detalles de productos/servicios con cantidades (hasta 6 decimales), precios unitarios, descuentos
- Impuestos: IVA, ICE, IRBPNR
- Formas de pago
- Información adicional (hasta 15 campos)

**XML generado se guarda en:** `comprobantes/generados/`

### 2. Firma Electrónica (XAdES_BES)

**Archivo:** `sistema/md_facturacion/autorizacion/procesos/firmar2.php`

Utiliza **QuijoteLuiFirmador** (Java JAR) para firmar el XML:

```
java -jar QuijoteLuiFirmador.jar <xml_entrada> <xml_salida> <cert.p12> <clave>
```

**Especificaciones de firma:**
| Parámetro | Valor |
|-----------|-------|
| Estándar | XAdES_BES |
| Versión | 1.3.2 |
| Tipo | ENVELOPED |
| Codificación | UTF-8 |
| Algoritmo | RSA-SHA1 |
| Longitud clave | 2048 bits |

**Librerías utilizadas:**
- MITyCLibXADES, MITyCLibTSA, MITyCLibAPI, MITyCLibOCSP, MITyCLibTrust

**XML firmado se guarda en:** `comprobantes/firmados/`

### 3. Envío al SRI (WS Recepción)

**Archivo:** `sistema/md_facturacion/autorizacion/procesos/envio2.php`

Envía el XML firmado al Web Service de **Recepción** del SRI:

```php
$cliente = new SoapClient($webServiceUrl, [
    'stream_context' => stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ])
]);
$respuesta = $cliente->validarComprobante(['xml' => $xml_firmado]);
```

**Respuestas posibles:**
| Estado | Significado |
|--------|-------------|
| `RECIBIDA` | Comprobante aceptado, pendiente de autorización |
| `DEVUELTA` | Error en el XML, debe corregirse |

**Indicador de error 65** (fecha extemporánea): Si el comprobante se envió después de 24h de la fecha de emisión. El sistema lo marca como "POR RECTIFICAR".

### 4. Consulta de Autorización (WS Autorización)

**Archivo:** `sistema/md_facturacion/autorizacion/procesos/autoriza2.php`

Consulta el estado del comprobante por clave de acceso:

```php
$cliente = new SoapClient($webServiceUrl);
$respuesta = $cliente->autorizacionComprobante([
    'claveAccesoComprobante' => $clave_acceso
]);
```

**Estados de respuesta:**
| Estado | Siglas | Acción |
|--------|--------|--------|
| AUTORIZADO | AUT | ✅ Válido - Generar RIDE y notificar |
| NO AUTORIZADO | NAT | ❌ Corregir y reenviar (misma clave) |
| EN PROCESAMIENTO | PPR | ⏳ Esperar (máx 24h) |

**XML autorizado se guarda en:** `comprobantes/autorizados/`

### 5. Generación RIDE (PDF)

**Archivo:** `sistema/md_facturacion/autorizacion/procesos/pdf.php` y `pdf2.php`

Genera la **Representación Impresa de Documento Electrónico (RIDE)** usando **TCPDF**:

- Toma el XML autorizado
- Extrae datos (emisor, receptor, productos, impuestos, totales)
- Genera PDF con formato según Anexo 2 de la Ficha Técnica SRI
- Incluye: clave de acceso (código de barras opcional), números de autorización, sellos

---

## 🌐 Web Services SRI

### Ambientes

| Ambiente | URL Base | Validez Tributaria |
|----------|----------|-------------------|
| **Pruebas** | `celcer.sri.gob.ec` | ❌ Sin validez |
| **Producción** | `cel.sri.gob.ec` | ✅ Con validez |

### Endpoints

#### Recepción de Comprobantes
```xml
Pruebas:  https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl
Producción: https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl
```

#### Autorización de Comprobantes
```xml
Pruebas:  https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl
Producción: https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl
```

#### Consulta de Comprobantes
```xml
Pruebas:  https://celcer.sri.gob.ec/comprobantes-electronicos-ws/ConsultaComprobante?wsdl
Producción: https://cel.sri.gob.ec/comprobantes-electronicos-ws/ConsultaComprobante?wsdl
```

#### Consulta de Factura Comercial Negociable
```xml
Pruebas:  https://celcer.sri.gob.ec/comprobantes-electronicos-ws/ConsultaFactura?wsdl
Producción: https://cel.sri.gob.ec/comprobantes-electronicos-ws/ConsultaFactura?wsdl
```

#### Devolución IVA Adultos Mayores (DIG)
```xml
Pruebas:  https://celcer.sri.gob.ec/devolucion-iva/rest
Producción: https://srienlinea.sri.gob.ec/devolucion-iva/rest
```

### Configuración de WS en el Sistema

La URL de producción está hardcodeada en el código (ej: `envio2.php`, `autoriza2.php`). Para cambiar entre ambientes:

- **Producción:** `https://cel.sri.gob.ec/...`
- **Pruebas:** `https://celcer.sri.gob.ec/...`

> ⚠️ **Nota:** No hardcodear certificados SSL en el código. Los certificados del SRI pueden cambiar sin previo aviso.

---

## 📄 Comprobantes Electrónicos Soportados

| Tipo | Código SRI | Estado |
|------|-----------|--------|
| Factura | 01 | ✅ Completo (v1.1.0) |
| Nota de Crédito | 04 | ✅ Completo (v1.1.0) |
| Nota de Débito | 05 | ✅ Completo |
| Liquidación de Compra | 03 | ✅ Completo (v1.1.0) |
| Guía de Remisión | 06 | 🚧 En desarrollo |
| Comprobante de Retención | 07 | 🚧 En desarrollo |

---

## 🗄️ Base de Datos

**Base de datos:** `contable`

### Tablas Principales

| Tabla | Descripción |
|-------|-------------|
| `usuarios` | Usuarios del sistema (autenticación) |
| `empresa` | Datos de empresas emisoras (RUC, razón social, certificado, clave) |
| `punto_emision` | Puntos de emisión (establecimiento + punto) |
| `facturas` | Cabecera de facturas (clave acceso, XML, estado, fechas) |
| `detalle_factura` | Productos/servicios de cada factura |
| `clientes` | Catálogo de clientes (RUC, cédula, pasaporte, datos fiscales) |
| `productos` | Catálogo de productos/servicios |
| `bodegas` | Bodegas/inventario |
| `proveedores` | Proveedores |
| `formas_pago` | Catálogo de formas de pago (códigos SRI) |
| `ambientes` | Ambientes (pruebas/producción) |
| `tipos_comprobante` | Tipos de comprobante SRI |
| `tipos_identificacion` | Tipos de identificación (RUC, cédula, etc.) |
| `impuestos` | Catálogo de impuestos |
| `tarifas_impuesto` | Tarifas de impuestos |
| `conceptos_pago` | Conceptos de pago |
| `auditoria_login` | Registro de intentos de login |
| `sesiones` | Sesiones activas |
| `suscripciones` | Planes de suscripción |
| `planes` | Planes disponibles |
| `correo_ajustes` | Configuración de correo |

---

## 📁 Estructura del Proyecto

```
factheo/
├── index.php                    # Login / Registro
├── factheo.sql                  # Dump de BD
├── css/                         # Estilos (sb-admin-2)
├── js/                          # JavaScript (sb-admin-2, Chart.js, DataTables)
├── img/                         # Imágenes (logo, fondo, Google Play)
├── vendor/                      # Librerías externas
│   ├── bootstrap/               # Framework CSS
│   ├── chart.js/                # Gráficos dashboard
│   ├── datatables/              # Tablas dinámicas
│   ├── fontawesome-free/        # Iconos
│   ├── jquery/                  # jQuery
│   ├── jquery-easing/           # Animaciones
│   └── TCPDF/                   # Generación PDF (RIDE)
├── sistema/                     # Código principal
│   ├── entorno/                 # Layout: nav, footer, meta, scripts, menú
│   ├── md_autenticacion/        # Login, registro, sesión, validación
│   ├── md_dashboard/            # Panel principal con KPIs
│   ├── md_facturacion/          # Módulo central de facturación
│   │   ├── facturacion_nueva.php, _graba.php, _lista.php, _ver.php
│   │   ├── facturacion_generar_xml_1_1_0.php   # Generación XML
│   │   ├── facturacion_completa.php             # Pipeline completo
│   │   ├── autorizacion/
│   │   │   ├── comprobantes/    # XML por estado
│   │   │   │   ├── generados/   # XML pre-firma
│   │   │   │   ├── firmados/    # XML con firma XAdES_BES
│   │   │   │   ├── autorizados/ # XML autorizados por SRI
│   │   │   │   ├── errores/     # Logs de error de firma
│   │   │   │   └── rechazados/  # XML rechazados
│   │   │   ├── librerias/       # QuijoteLuiFirmador (JAR), TCPDF
│   │   │   └── procesos/        # firmar2.php, envio2.php, autoriza2.php, pdf.php
│   │   └── XML_y_XSD_Factura/   # Schemas XSD oficiales SRI
│   ├── md_clientes/             # Gestión de clientes
│   ├── md_productos/            # Gestión de productos
│   ├── md_bodegas/              # Gestión de bodegas
│   ├── md_proveedores/          # Gestión de proveedores
│   ├── md_empresa/              # Datos de empresa + certificados + logos
│   ├── md_punto_emision/        # Puntos de emisión
│   ├── md_formas_pago/          # Formas de pago
│   ├── md_nota_credito/         # Notas de crédito
│   ├── md_nota_debito/          # Notas de débito
│   ├── md_liquidacion_compra/   # Liquidaciones de compra
│   ├── md_config/               # Ajustes + conexión BD
│   ├── md_registros/            # Logs de actividad
│   ├── md_plantilla/            # Plantillas (décimos, etc.)
│   ├── md_suscripciones/        # Planes de suscripción
│   └── sesiones/                # Archivos de sesión PHP
└── .gitignore                   # Archivos ignorados
```

---

## 🛠️ Mantenimiento

### Actualizar tarifas de IVA/ICE

Si el SRI cambia las tarifas, modificar:
- `facturacion_generar_xml_1_1_0.php`: variable `$codigo_porcentaje_iva` y `$tarifa_iva`
- Verificar tabla 17 (IVA) y tabla 18 (ICE) según la Ficha Técnica

### Tamaño máximo de archivos

En `php.ini`:
```ini
upload_max_filesize = 32M
post_max_size = 32M
memory_limit = 128M
max_execution_time = 300
```

### Logs del sistema

Los errores de firma se registran en:
```
sistema/md_facturacion/autorizacion/comprobantes/errores/log_firma_*.txt
```

El sistema también mantiene logs de:
- Intentos de login (`auditoria_login`)
- Sesiones activas (`sesiones`)
- Datos de sesión PHP (`sistema/sesiones/`)

### Respaldo

```bash
# Respaldar BD
mysqldump -u root -p contable > backup_$(date +%Y%m%d).sql

# Respaldar certificados
tar -czf certificados_$(date +%Y%m%d).tar.gz sistema/md_empresa/certificados/
```

---

## 🚢 Despliegue

### Desarrollo (local)
```
Ruta: /home/usuario/html/factheo/
Servidor: Apache + PHP 8.1 + MariaDB
```

### Producción
1. Migrar BD y archivos al servidor de producción
2. Configurar `conexion.php` con credenciales de producción
3. Asegurar que Java (JRE) esté instalado
4. Verificar conectividad con WS del SRI (puerto 443)
5. Configurar HTTPS obligatorio
6. Proteger directorios sensibles con `.htaccess`:
```
# .htaccess en md_empresa/certificados/
Deny from all
```
7. Configurar cron para limpieza de logs/comprobantes antiguos (opcional)

---

## 📝 Notas Técnicas

- **Generación XML:** Utiliza DOMDocument de PHP para construir XML v1.1.0
- **Clave de acceso:** 49 dígitos generados con algoritmo Módulo 11
- **Firma:** Se invoca Java JAR externo (QuijoteLuiFirmador) vía `exec()`
- **WS SRI:** Consumo SOAP vía `SoapClient` de PHP
- **RIDE:** TCPDF para generación de PDF con formato oficial SRI
- **Sesiones:** PHP nativas, almacenadas en archivos en `sistema/sesiones/`
- **Multi-empresa:** Una instalación puede manejar múltiples RUC
- **Estados XML:** `PENDIENTE` → `GENERADO` → `FIRMADO` → `ENVIADO` → `RECIBIDA` → `AUTORIZADO` / `NO AUTORIZADO`

---

## 📄 Ficha Técnica SRI

La documentación técnica completa del SRI está disponible en el archivo:

```
FICHA TE_CNICA COMPROBANTES ELECTRO_NICOS ESQUEMA OFFLINE Versio_n 231.pdf
```

**Versión:** 2.31 — Abril 2025
**Incluye:** Formatos XML v1.0.0/v1.1.0, Web Services, códigos de error 35-92, ICE, retenciones, RIDE, Anexos 1-24, RIMPE, Grandes Contribuyentes, DIG, materiales construcción.

---

## 👨‍💻 Autor

**@SoyFenixEC** — Desarrollo y mantenimiento de Factheo

---

## ⚠️ Aviso Legal

Este sistema genera comprobantes electrónicos con validez tributaria en Ecuador. Es responsabilidad del usuario:
- Mantener actualizado el certificado de firma electrónica
- Verificar la vigencia del RUC
- Cumplir con las obligaciones tributarias ante el SRI
- Realizar pruebas en ambiente de certificación antes de producir

---

> *Factheo — Facturación Electrónica Ecuador* 🇪🇨

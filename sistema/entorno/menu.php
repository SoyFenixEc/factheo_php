<!-- Sidebar - Menú Principal -->
<a class="sidebar-brand d-flex align-items-center justify-content-center" href="../md_dashboard/dashboard.php">
    <div class="sidebar-brand-icon">
        <i class="fas fa-cloud"></i>
    </div>
    <div class="sidebar-brand-text mx-3"><strong>F</strong>ac<strong>T</strong>heo</div>
</a>

<!-- Divider -->
<hr class="sidebar-divider my-0">

<!-- Dashboard -->
<li class="nav-item">
    <a class="nav-link" href="../md_dashboard/dashboard.php">
        <i class="fas fa-home"></i>
        <span>Inicio</span>
    </a>
</li>

<!-- Divider -->
<hr class="sidebar-divider">

<!-- Heading Emisión -->
<div class="sidebar-heading">
    Emisión
</div>

<!-- Emisión - Comprobantes -->
<li class="nav-item">
    <a class="nav-link" href="../md_facturacion/facturacion_lista.php">
        <i class="fas fa-file-invoice-dollar"></i>
        <span>Facturas</span>
    </a>
</li>

<li class="nav-item">
    <a class="nav-link" href="../md_nota_credito/nota_credito_lista.php">
        <i class="fas fa-sticky-note"></i>
        <span>Nota de Crédito</span>
    </a>
</li>
<li class="nav-item">
    <a class="nav-link" href="../md_nota_debito/nota_debito_lista.php">
        <i class="fas fa-file-invoice"></i>
        <span>Nota de Débito</span>
    </a>
</li>
<li class="nav-item">
    <a class="nav-link" href="guia_remision.html">
        <i class="fas fa-truck-loading"></i>
        <span>Guía de Remisión</span>
    </a>
</li>
<li class="nav-item">
    <a class="nav-link" href="comprobante_retencion.html">
        <i class="fas fa-hand-holding-usd"></i>
        <span>Retención</span>
    </a>
</li>
<li class="nav-item">
    <a class="nav-link" href="../md_liquidacion_compra/liquidacion_compra_lista.php">
        <i class="fas fa-shopping-cart"></i>
        <span>Liquidación de Compras</span>
    </a>
</li>

<!-- Divider -->
<hr class="sidebar-divider">

<!-- Heading Datos -->
<div class="sidebar-heading">
    Datos
</div>

<!-- Datos -->
<li class="nav-item">
    <a class="nav-link" href="../md_bodegas/bodega_lista.php">
        <i class="fas fa-warehouse"></i>
        <span>Bodegas</span>
    </a>
</li>
<li class="nav-item">
    <a class="nav-link" href="../md_productos/producto_lista.php">
        <i class="fas fa-box"></i>
        <span>Productos</span>
    </a>
</li>
<li class="nav-item">
    <a class="nav-link" href="../md_clientes/cliente_lista.php">
        <i class="fas fa-users"></i>
        <span>Clientes</span>
    </a>
</li>
<!--
<li class="nav-item">
    <a class="nav-link" href="../md_proveedores/proveedores_lista.php">
        <i class="fas fa-truck"></i>
        <span>Proveedores</span>
    </a>
</li>
-->
<!-- Divider -->
<hr class="sidebar-divider">

<!-- Heading Configuración -->
<div class="sidebar-heading">
    Ajustes
</div>

<!-- Configuración -->
<li class="nav-item">
    <a class="nav-link" href="../md_empresa/empresa_lista.php">
        <i class="fas fa-building"></i>
        <span>Empresas</span>
    </a>
</li>
<li class="nav-item">
    <a class="nav-link" href="../md_punto_emision/punto_emision_lista.php">
        <i class="fas fa-store"></i>
        <span>Puntos de emisión</span>
    </a>
</li>
<!--
<li class="nav-item">
    <a class="nav-link" href="../md_config/ajustes.php">
        <i class="fas fa-cog"></i>
        <span>Ajustes</span>
    </a>
</li>

<li class="nav-item">
    <a class="nav-link" href="../md_formas_pago/formas_pago_lista.php">
        <i class="fas fa-credit-card"></i>
        <span>Formas de pago</span>
    </a>
</li>
--> 

<!-- Divider -->
<hr class="sidebar-divider d-none d-md-block">

<!-- Sidebar Toggler -->
<div class="text-center d-none d-md-inline">
    <button class="rounded-circle border-0" id="sidebarToggle"></button>
</div>

<style>
.sidebar-heading {
    padding: 0 1rem;
    font-weight: 800;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 1.5rem;
    margin-bottom: 0.5rem;
}

.nav-item {
    position: relative;
    margin-bottom: 5px;
}

.nav-link {
    color: rgba(255, 255, 255, 0.8);
    padding: 0.75rem 1rem;
    transition: all 0.3s;
    display: flex;
    align-items: center;
}

.nav-link:hover {
    color: #fff;
    background-color: rgba(255, 255, 255, 0.15);
}

.nav-link.active {
    color: #fff;
    background-color: rgba(255, 255, 255, 0.15);
    font-weight: 700;
}

.nav-link.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 4px;
    background: white;
}

.nav-link i {
    margin-right: 0.75rem;
    width: 20px;
    text-align: center;
}
</style>
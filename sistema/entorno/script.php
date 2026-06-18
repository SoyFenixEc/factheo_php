<!-- Botón para scroll al inicio -->
<a class="scroll-to-top rounded bg-dark" href="#page-top">
    <i class="fas fa-angle-up"></i>
</a>

<!-- jQuery (debe ir primero) -->
<script src="/vendor/jquery/jquery.min.js"></script>

<!-- Bootstrap 5 Bundle (incluye Popper.js) - ¡ASEGÚRATE DE QUE SEA VERSIÓN 5! -->
<script src="/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- jQuery Easing -->
<script src="/vendor/jquery-easing/jquery.easing.min.js"></script>

<!-- SweetAlert2 -->
<script src="/vendor/sweetalert2/sweetalert2.all.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

<!-- Scripts personalizados de la plantilla -->
<script src="/js/sb-admin-2.min.js"></script>

<!-- Scripts para gráficos demo (si los usas) -->
<script src="/js/demo/chart-area-demo.js"></script>
<script src="/js/demo/chart-pie-demo.js"></script>

<!-- Tus funciones personalizadas -->
<script src="/js/funciones.js"></script>

<!-- Script para manejar el spinner -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    window.addEventListener('load', function () {
        const spinner = document.getElementById("spinner");
        if (spinner) {
            spinner.style.display = "none";
        }
    });
});
</script>
function toggleDanfeVisibility() {
    console.log("Botão clicado!"); // Debug
    let danfeDiv = document.getElementById('danfe-visualizacao');
    let toggleBtn = document.getElementById('toggle-danfe-btn');
    
    console.log("Estado atual:", danfeDiv.style.display); // Debug
    
    if (danfeDiv.style.display === 'none' || danfeDiv.style.display === '') {
        danfeDiv.style.display = 'block';
        toggleBtn.textContent = 'Ocultar DANFE';
        console.log("Mostrando DANFE"); // Debug
    } else {
        danfeDiv.style.display = 'none';
        toggleBtn.textContent = 'Ver DANFE Completo';
        console.log("Ocultando DANFE"); // Debug
    }
}

document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM carregado"); // Debug
    const toggleBtn = document.getElementById('toggle-danfe-btn');
    if (toggleBtn) {
        console.log("Botão encontrado"); // Debug
        toggleBtn.addEventListener('click', toggleDanfeVisibility);
    } else {
        console.log("Botão NÃO encontrado"); // Debug
    }
});
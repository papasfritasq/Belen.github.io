let cantidad = 2;

function sumar(){
    cantidad++;
    document.getElementById("cant").textContent = cantidad;
}

function restar(){
    if(cantidad > 1){
        cantidad--;
        document.getElementById("cant").textContent = cantidad;
    }
}
 <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <style>
              body {
                                          /* Estilos para el fondo de pantalla */
                                          background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('https://tripates.com/media/canada-alberta-lake-louise-best-campgrounds-rv-camping-area-bridge.jpg');
                                          background-size: cover;
                                          background-position: center center;
                                          background-attachment: fixed;
                                          /*color: #f8f9fa; *//* Color del texto blanco para mayor contraste */
                                          /*text-shadow: 2px 2px 4px #000000;*/ /* Sombra al texto para mejorar la legibilidad */
                            }
                            .mb-4{
                                color: white;
                            }
                            

                            /* Aplica un borde a los inputs y selects del modal de edición */
                            #form-editar-acampante .form-control-sm,
                            #form-editar-acampante .form-select-sm {
                                border: 1px solid #979ca1; /* Color de borde por defecto de Bootstrap */
                            }

                             .is-invalid {
                                  border-color: #dc3545 !important;
                            }

                            /* Oculta el input original */
                            .input-file-oculto {
                                display: none;
                            }

                            /* Estilos para el botón personalizado */
                            .custom-file-button {
                                cursor: pointer; /* Muestra el cursor de mano para indicar que es clicable */
                            }

                            /* Estilos para las tarjetas y tablas */
                            .container {
                                          background-color: rgba(163, 154, 154, 0.62);/*(0, 0, 0, 0.6);*/ /* Fondo semi-transparente para el contenedor principal */
                                          padding: 20px;
                                          border-radius: 8px;
                            }
                            .table {
                                          color: #f8f9fa;
                            }
                            .table-bordered {
                                          border-color: #6c757d;
                            }
                            .table-hover tbody tr:hover {
                                          background-color: rgba(255, 255, 255, 0.2);
                            }
                            .table-dark, .table-dark th {
                                          background-color: #212529 !important;
                                          border-color: #454d55;
                            }
                            
                            /* ESTILOS PERSONALIZADOS PARA LA BARRA DE DESPLAZAMIENTO HORIZONTAL */
                            /* Para navegadores basados en WebKit (Chrome, Safari, Edge, etc.) */
                            .table-responsive::-webkit-scrollbar {
                                          height: 10px; /* Altura de la barra de desplazamiento */
                            }
                            .table-responsive::-webkit-scrollbar-track {
                                          background: #343a40; /* Color de fondo de la barra de desplazamiento */
                                          border-radius: 10px;
                            }
                            .table-responsive::-webkit-scrollbar-thumb {
                                          background: #ced4da; /* Color del "pulgar" de la barra de desplazamiento */
                                          border-radius: 10px;
                            }
                            .table-responsive::-webkit-scrollbar-thumb:hover {
                                          background: #adb5bd; /* Color al pasar el cursor sobre el "pulgar" */
                            }
                            /* Para navegadores Firefox */
                            .table-responsive {
                                          scrollbar-color: #adb5bd #343a40;
                            }
       </style>
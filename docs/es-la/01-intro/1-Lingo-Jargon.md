# Glosario/Jerga de Airship 

## Términos y Conceptos Técnicos de Airship

### Tipos de Extensión de Airship

Cuando [desarrollamos extensiones personalizadas de CMS Airship](../04-developer-docs),
encontramos tres tipos de extensiones soportadas:

1. **Cabins**
   * Cabins son aplicaciones autocontenidas. Pueden tener sus propios Gadgets
     y Motifs desarrollados específicamente para ellas. También se pueden aplicar
     Gadgets Universales y Motifs en ellas.

     Por ejemplo, si quisiera hacer su propio carrito de compras en Airship, sería
     más probable que quisiera desarrollar una Cabin en lugar de extender la funcionalidad
     de Cabins existentes (Bridge and Hull).
2. **Gadgets**
   * La función de los Gadgets es afectar el *comportamiento* de una Cabin existente (o de
     todas las Cabins). Desde un Gadget, puede extender el funcionamiento de las
     características del marco de trabajo (mediante la API Gears que proveemos),
     agregar nuevos Landings a Cabins, y mucho más.
3. **Motifs**
   * Los Motifs se usan para afectar la *apariencia* de una Cabin existente (o
     de todas las Cabins).Un Motif puede anular toda la plantilla base o definir nuevos 
     archivos CSS o de Javascript.

### Directorios Principales de Airship

* `Alerts`  -> Excepciones
  * Define las Excepciones y Errores específicos del marco de trabajo que
    pudieran ser arrojados
* `Cabin`   -> Leer la sección de arriba
  * Cada Cabin viene con sus propios Blueprints, Landings, Lenses, Motifs, y
    Gadgets opcionales
* `Engine`  -> Archivos principales del marco de trabajo
  * Estas clases hacen funcionar a Airship en su totalidad; la mayoría pueden ser mejoradas
* `Gadgets` -> Leer la sección de arriba
  * Añaden funcionalidad a una Cabin ya establecida (o a todas las cabins). Existen como un
    Archivero de PHP (.phar) y su firma Ed25519 asociada.
* `Installer` -> Instalador
  * Nuestro código instalador se encuentra auto-contenido y está estrictamente fuera de
    la raíz del documento
* `Motifs`  -> Leer la sección de arriba
  * Plantillas y hojas de estilo provistas por nuestra comunidad. Pueden ser asignados a una
    Cabin en específico o universalmente.
* `config`  -> Configuración
* `lang`    -> Archivos específicos del lenguaje (para internationalización)
* `public`  -> Raíz web pública (Su webserver debe apuntar aquí)

### Arquitectura de Airship

Nuestra arquitectura es *similar* a MVC. Además de hacer la terminología 
temáticamente apropiada, no tenemos objetos vista, simplemente tenemos plantillas
(rendered by Twig).

Adicionalmente, adoptar nuestra propia jerga nos ofrece cierta flexibilidad en nuestras 
decisiones de diseño sin agraviar a los puristas. **El MVC verdadero** no tiene mucho
sentido en aplicaciones PHP de todas formas.

#### Blueprint ~~ Modelo

Un Blueprint is análogo a un Model in un marco de trabajo traditional de MVC.
Debería ser responsable de manejar las interacciones de la base de datos.

#### Lens ~~ View

Un Lens es análogo a una View en un marco de trabajo tradicional de MVC.
Lenses son archivos de plantilla renderizados por Twig.

#### Landing ~~ Controller

A Landing is análogo a un Controller en un marco de trabajo tradicional de MVC. Landings
son los destinos donde aterrizan sus pasajeros. Landings son típicamente independientes de la base de datos
que se use y en su mayoría se encargan de pasr información al Blueprint y después a la
plantilla.

## Cultura de Airship

### Tripulación

Colectivamente se refiere a los Ingenieros, Pilotos y Pasajeros.

### Ingeniero

An engineer is someone who develops Motifs, Gadgets, or Cabins for their own
Airship or for others'.

### Piloto

Un piloto es un usuario administrador. Pueden compartir cierta responsabilidad con sus
copilotos, pero esta nave vuela bajo sus reglas.

### Pasajero

Un usuario sin privilegios. Solo estàn aquì por la aventura. La mayorìa de los blogs los llama lectores,
pero eso carece de imaginaciòn.¿En dònde quedò su sentido de la aventura?

[Siguiente: Instalando Airship](2-Installing.md)

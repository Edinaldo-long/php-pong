# 🏓 PHP Pong Arcade

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5_Canvas-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![WebSockets](https://img.shields.io/badge/WebSockets-Nativo-00F3FF?style=for-the-badge)

Um jogo arcade de Ping-Pong multiplayer em tempo real desenvolvido com **PHP puro (Sockets/WebSockets nativos)** no back-end autoritativo e **HTML5 Canvas + Web Audio API** no front-end — **zero dependências externas**, sem Composer e sem arquivos de áudio externos.

---

## ✨ Destaques Técnicos e Mecânicas

* **Servidor Autoritativo em PHP Puro (`servidor.php`):** Implementação completa do protocolo RFC 6455 (handshake WebSocket, codificação e decodificação de múltiplos frames TCP simultâneos) rodando a ~60 FPS (`usleep(16000)`).
* **Multiplayer Automático ou Modo Solo:** O primeiro cliente conectado assume a raquete esquerda (`Player 1`). Se um segundo navegador se conectar, assume a raquete direita (`Player 2`); caso contrário, uma IA adaptativa assume o controle.
* **Física Arcade Avançada:**
  * **Controle Direcional:** O ângulo de rebote varia conforme o ponto de impacto na raquete.
  * **Efeito Curva (*Curveball / Spin*):** Movimentar a raquete no momento da colisão aplica rotação lateral à trajetória da bola.
  * **Super Chute (*Power Shot*):** Carrega energia na raquete ao segurar `Espaço`, disparando a bola em alta velocidade com rastro flamejante.
* **Power-Ups Dinâmicos:** Orbes surgem periodicamente no centro da quadra (`+` Aumenta raquete, `-` Encolhe rival, `⚡` Turbo instantâneo).
* **Áudio e Efeitos Procedurais:** Sintetizador retrô via *Web Audio API*, sistema de partículas nas colisões e *screen shake* nos impactos fortes.

---

## 🎮 Controles

| Tecla / Ação | Função |
| :--- | :--- |
| **`W` / `S`** ou **`▲` / `▼`** | Mover raquete e aplicar efeito curva na batida |
| **`Espaço`** (segurar) | Carregar **Super Chute** (*Power Shot*) |
| **`P`** ou **`Esc`** | Pausar / Retomar a partida |
| **`Enter`** | Reiniciar partida após o placar final (7 pontos) |

---

## 🚀 Como Executar Localmente

### Pré-requisitos
* **PHP 8.0+** (via XAMPP, Laragon ou instalação local) com a extensão `sockets` habilitada.
* Navegador moderno com suporte a HTML5 Canvas e WebSockets.

### Passo a passo

1. **Clone o repositório:**
   ```bash
   git clone [https://github.com/Edinaldo-long/php-pong.git](https://github.com/Edinaldo-long/php-pong.git)
   cd php-pong

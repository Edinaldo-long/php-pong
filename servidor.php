<?php
set_time_limit(0);
$host = '0.0.0.0';
$port = 8000;

$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($server, $host, $port);
socket_listen($server);
socket_set_nonblock($server);

echo "Servidor PHP Pong ARCADE (Corrigido) rodando na porta $port...\n";

$clients = [];
$handshakes = [];
$lados = [];

function criarEstadoInicial() {
    return [
        'largura' => 800,
        'altura' => 450,
        'pausado' => false,
        'vencedor' => null,
        'ultimoRebatedor' => null,
        'eventoSom' => null,
        'shake' => 0,
        'bola' => [
            'x' => 400, 'y' => 225,
            'vx' => 5.5, 'vy' => 2.0,
            'spin' => 0.0,
            'raio' => 8,
            'power' => false
        ],
        'jogadores' => [
            'esq' => ['y' => 175, 'altura' => 85, 'alturaBase' => 85, 'largura' => 14, 'pontos' => 0, 'movendo' => 0, 'carregando' => false, 'buffTimer' => 0],
            'dir' => ['y' => 175, 'altura' => 85, 'alturaBase' => 85, 'largura' => 14, 'pontos' => 0, 'movendo' => 0, 'carregando' => false, 'buffTimer' => 0]
        ],
        'powerup' => [
            'ativo' => false,
            'x' => 400, 'y' => 225,
            'tipo' => 'crescer',
            'timerSpawn' => 180
        ]
    ];
}

$gameState = criarEstadoInicial();

function encodeWS($text) {
    $b1 = 0x81;
    $length = strlen($text);
    if ($length <= 125) {
        $header = pack('CC', $b1, $length);
    } elseif ($length < 65536) {
        $header = pack('CCn', $b1, 126, $length);
    } else {
        $header = pack('CCNN', $b1, 127, 0, $length);
    }
    return $header . $text;
}

// NOVA FUNÇÃO: Lê múltiplos frames WebSocket grudados no mesmo pacote
function decodeMultipleWS($data) {
    $mensagens = [];
    $offset = 0;
    $totalLen = strlen($data);

    while ($offset + 2 <= $totalLen) {
        $length = ord($data[$offset + 1]) & 127;
        $headerSize = 2;

        if ($length == 126) {
            if ($offset + 4 > $totalLen) break;
            $length = unpack('n', substr($data, $offset + 2, 2))[1];
            $headerSize = 4;
        } elseif ($length == 127) {
            if ($offset + 10 > $totalLen) break;
            $length = unpack('J', substr($data, $offset + 2, 8))[1];
            $headerSize = 10;
        }

        $maskOffset = $offset + $headerSize;
        $payloadOffset = $maskOffset + 4;
        $frameEnd = $payloadOffset + $length;

        if ($frameEnd > $totalLen) break;

        $masks = substr($data, $maskOffset, 4);
        $payload = substr($data, $payloadOffset, $length);

        $text = '';
        for ($i = 0; $i < $length; ++$i) {
            $text .= $payload[$i] ^ $masks[$i % 4];
        }
        $mensagens[] = $text;
        $offset = $frameEnd;
    }

    return $mensagens;
}

$iaErroY = 0;
$iaTimerErro = 0;

while (true) {
    $gameState['jogadores']['esq']['movendo'] = 0;
    $gameState['jogadores']['dir']['movendo'] = 0;
    $gameState['eventoSom'] = null;
    if ($gameState['shake'] > 0) $gameState['shake']--;

    if ($newClient = @socket_accept($server)) {
        socket_set_nonblock($newClient);
        $id = spl_object_id($newClient);
        $clients[$id] = $newClient;
        $handshakes[$id] = false;
    }

    foreach ($clients as $id => $client) {
        $data = @socket_read($client, 4096);
        if ($data === false) {
            $err = socket_last_error($client);
            if ($err !== 10035 && $err !== 11 && $err !== 0) {
                unset($clients[$id], $handshakes[$id], $lados[$id]);
                @socket_close($client);
            }
            continue;
        }
        if ($data === '') {
            unset($clients[$id], $handshakes[$id], $lados[$id]);
            @socket_close($client);
            continue;
        }

        if (!$handshakes[$id]) {
            if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $data, $match)) {
                $key = base64_encode(pack('H*', sha1(trim($match[1]) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
                $upgrade = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $key\r\n\r\n";
                socket_write($client, $upgrade, strlen($upgrade));
                $handshakes[$id] = true;

                if (!in_array('esq', $lados)) {
                    $lados[$id] = 'esq';
                } elseif (!in_array('dir', $lados)) {
                    $lados[$id] = 'dir';
                } else {
                    $lados[$id] = 'espectador';
                }

                $boasVindas = encodeWS(json_encode(['tipo' => 'boas-vindas', 'lado' => $lados[$id]]));
                @socket_write($client, $boasVindas);
            }
            continue;
        }

        // Processa TODAS as mensagens que chegaram juntas no buffer
        $listaTextos = decodeMultipleWS($data);
        foreach ($listaTextos as $texto) {
            $msg = json_decode($texto, true);
            if (!$msg || !isset($lados[$id]) || $lados[$id] === 'espectador') continue;
            $lado = $lados[$id];

            if ($msg['tipo'] === 'pausar' && $gameState['vencedor'] === null) {
                $gameState['pausado'] = !$gameState['pausado'];
                echo $gameState['pausado'] ? "Jogo PAUSADO\n" : "Jogo RETOMADO\n";
            }

            if ($msg['tipo'] === 'reiniciar' && $gameState['vencedor'] !== null) {
                $gameState = criarEstadoInicial();
            }

            if (!$gameState['pausado']) {
                if ($msg['tipo'] === 'carregar') {
                    $gameState['jogadores'][$lado]['carregando'] = (bool)$msg['ativo'];
                }

                if ($msg['tipo'] === 'mover') {
                    $velocidadeRaquete = $gameState['jogadores'][$lado]['carregando'] ? 6 : 10;
                    if ($msg['direcao'] === 'subir') {
                        $gameState['jogadores'][$lado]['y'] = max(0, $gameState['jogadores'][$lado]['y'] - $velocidadeRaquete);
                        $gameState['jogadores'][$lado]['movendo'] = -1;
                    } elseif ($msg['direcao'] === 'descer') {
                        $maxY = $gameState['altura'] - $gameState['jogadores'][$lado]['altura'];
                        $gameState['jogadores'][$lado]['y'] = min($maxY, $gameState['jogadores'][$lado]['y'] + $velocidadeRaquete);
                        $gameState['jogadores'][$lado]['movendo'] = 1;
                    }
                }
            }
        }
    }

    if (!empty($handshakes) && $gameState['vencedor'] === null && !$gameState['pausado']) {
        foreach (['esq', 'dir'] as $l) {
            if ($gameState['jogadores'][$l]['buffTimer'] > 0) {
                $gameState['jogadores'][$l]['buffTimer']--;
                if ($gameState['jogadores'][$l]['buffTimer'] === 0) {
                    $gameState['jogadores'][$l]['altura'] = $gameState['jogadores'][$l]['alturaBase'];
                }
            }
        }

        if (!in_array('dir', $lados)) {
            $iaTimerErro--;
            if ($iaTimerErro <= 0) {
                $iaErroY = rand(-32, 32);
                $iaTimerErro = rand(25, 55);
            }

            $pd = &$gameState['jogadores']['dir'];
            $centro = $pd['y'] + ($pd['altura'] / 2);
            $alvoY = ($gameState['bola']['vx'] > 0) ? ($gameState['bola']['y'] + $iaErroY) : ($gameState['altura'] / 2);
            $velIA = $gameState['bola']['power'] ? 4.3 : 5.0;

            if ($alvoY > $centro + 12) {
                $pd['y'] = min($gameState['altura'] - $pd['altura'], $pd['y'] + $velIA);
                $pd['movendo'] = 1;
            } elseif ($alvoY < $centro - 12) {
                $pd['y'] = max(0, $pd['y'] - $velIA);
                $pd['movendo'] = -1;
            }
        }

        $pu = &$gameState['powerup'];
        if (!$pu['ativo']) {
            $pu['timerSpawn']--;
            if ($pu['timerSpawn'] <= 0) {
                $tipos = ['crescer', 'encolher', 'turbo'];
                $pu['tipo'] = $tipos[array_rand($tipos)];
                $pu['x'] = rand(280, 520);
                $pu['y'] = rand(70, 380);
                $pu['ativo'] = true;
            }
        }

        $bola = &$gameState['bola'];
        $bola['vy'] += $bola['spin'];
        $bola['vy'] = max(-11, min(11, $bola['vy']));
        $bola['x'] += $bola['vx'];
        $bola['y'] += $bola['vy'];

        if ($pu['ativo'] && $gameState['ultimoRebatedor'] !== null) {
            $dist = hypot($bola['x'] - $pu['x'], $bola['y'] - $pu['y']);
            if ($dist <= $bola['raio'] + 18) {
                $quem = $gameState['ultimoRebatedor'];
                $rival = ($quem === 'esq') ? 'dir' : 'esq';

                if ($pu['tipo'] === 'crescer') {
                    $gameState['jogadores'][$quem]['altura'] = 135;
                    $gameState['jogadores'][$quem]['buffTimer'] = 600;
                } elseif ($pu['tipo'] === 'encolher') {
                    $gameState['jogadores'][$rival]['altura'] = 45;
                    $gameState['jogadores'][$rival]['buffTimer'] = 600;
                } elseif ($pu['tipo'] === 'turbo') {
                    $bola['vx'] *= 1.35;
                    $bola['power'] = true;
                }

                $pu['ativo'] = false;
                $pu['timerSpawn'] = 300;
                $gameState['eventoSom'] = 'powerup';
                $gameState['shake'] = 6;
            }
        }

        if ($bola['y'] - $bola['raio'] <= 0) {
            $bola['y'] = $bola['raio'];
            $bola['vy'] *= -1;
            $bola['spin'] *= 0.5;
            $gameState['eventoSom'] = 'parede';
        } elseif ($bola['y'] + $bola['raio'] >= $gameState['altura']) {
            $bola['y'] = $gameState['altura'] - $bola['raio'];
            $bola['vy'] *= -1;
            $bola['spin'] *= 0.5;
            $gameState['eventoSom'] = 'parede';
        }

        $pe = $gameState['jogadores']['esq'];
        if ($bola['vx'] < 0 &&
            $bola['x'] - $bola['raio'] <= 29 && $bola['x'] - $bola['raio'] >= 10 &&
            $bola['y'] >= $pe['y'] - 6 && $bola['y'] <= $pe['y'] + $pe['altura'] + 6) {
            
            $centro = $pe['y'] + ($pe['altura'] / 2);
            $impacto = max(-1, min(1, ($bola['y'] - $centro) / ($pe['altura'] / 2)));

            $velBase = min(13, abs($bola['vx']) + 0.35);
            if ($pe['carregando']) {
                $velBase = min(17.5, $velBase + 4.5);
                $bola['power'] = true;
                $gameState['shake'] = 10;
                $gameState['eventoSom'] = 'power';
            } else {
                $bola['power'] = false;
                $gameState['eventoSom'] = 'raquete';
            }

            $bola['vx'] = $velBase;
            $bola['vy'] = ($impacto * 7.5) + ($pe['movendo'] * 1.5);
            $bola['spin'] = $pe['movendo'] * 0.14;
            $gameState['ultimoRebatedor'] = 'esq';
        }

        $pd = $gameState['jogadores']['dir'];
        if ($bola['vx'] > 0 &&
            $bola['x'] + $bola['raio'] >= $gameState['largura'] - 29 && $bola['x'] + $bola['raio'] <= $gameState['largura'] - 10 &&
            $bola['y'] >= $pd['y'] - 6 && $bola['y'] <= $pd['y'] + $pd['altura'] + 6) {
            
            $centro = $pd['y'] + ($pd['altura'] / 2);
            $impacto = max(-1, min(1, ($bola['y'] - $centro) / ($pd['altura'] / 2)));

            $velBase = min(13, abs($bola['vx']) + 0.35);
            if ($pd['carregando']) {
                $velBase = min(17.5, $velBase + 4.5);
                $bola['power'] = true;
                $gameState['shake'] = 10;
                $gameState['eventoSom'] = 'power';
            } else {
                $bola['power'] = false;
                $gameState['eventoSom'] = 'raquete';
            }

            $bola['vx'] = -$velBase;
            $bola['vy'] = ($impacto * 7.5) + ($pd['movendo'] * 1.5);
            $bola['spin'] = $pd['movendo'] * 0.14;
            $gameState['ultimoRebatedor'] = 'dir';
        }

        if ($bola['x'] < 0) {
            $gameState['jogadores']['dir']['pontos']++;
            $gameState['eventoSom'] = 'ponto';
            $gameState['shake'] = 14;
            resetarBola($bola, 1);
        } elseif ($bola['x'] > $gameState['largura']) {
            $gameState['jogadores']['esq']['pontos']++;
            $gameState['eventoSom'] = 'ponto';
            $gameState['shake'] = 14;
            resetarBola($bola, -1);
        }

        if ($gameState['jogadores']['esq']['pontos'] >= 7) {
            $gameState['vencedor'] = 'PLAYER 1';
        } elseif ($gameState['jogadores']['dir']['pontos'] >= 7) {
            $gameState['vencedor'] = in_array('dir', $lados) ? 'PLAYER 2' : 'CPU';
        }
    }

    if (!empty($handshakes)) {
        $payload = encodeWS(json_encode(['tipo' => 'atualizacao', 'estado' => $gameState]));
        foreach ($clients as $id => $client) {
            if (!empty($handshakes[$id])) {
                @socket_write($client, $payload);
            }
        }
    }

    usleep(16000);
}

function resetarBola(&$bola, $dir) {
    $bola['x'] = 400;
    $bola['y'] = 225;
    $bola['vx'] = $dir * 5.5;
    $bola['vy'] = (rand(0, 1) ? 1 : -1) * 2.5;
    $bola['spin'] = 0.0;
    $bola['power'] = false;
}
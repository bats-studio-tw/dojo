#!/bin/bash

echo "🧪 WebSocket 配置测试脚本"
echo "========================="

# 配置变量 (请修改为你的域名)
WS_HOST="ws.yourdomain.com"
MAIN_HOST="yourdomain.com" 

echo ""
echo "1. 测试 DNS 解析..."
nslookup $WS_HOST

echo ""
echo "2. 测试 SSL 证书..."
curl -I https://$WS_HOST/health

echo ""
echo "3. 测试 WebSocket 连接..."
curl -I \
  -H "Connection: Upgrade" \
  -H "Upgrade: websocket" \
  -H "Sec-WebSocket-Version: 13" \
  -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" \
  https://$WS_HOST/

echo ""
echo "4. 测试主站点 WebSocket API..."
curl -X GET https://$MAIN_HOST/websocket/status

echo ""
echo "✅ 测试完成！"

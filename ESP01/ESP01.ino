#include <ESP8266WiFi.h> // library untuk akses wifi
#include <ESP8266HTTPClient.h> // untuk post data (count) ke database/web dashboard

#define WIFI_SSID "hi" // nama wifi/ssid
#define WIFI_PASSWORD "afiq12345" // password bagi wifi/ssid
#define SERVER_URL "http://10.93.119.30/smart_classroom/test/simpan.php" // esp8266 hantar data kepada url/server berikut melalui kaedah http post
#define GET_LED_URL "http://10.93.119.30/smart_classroom/test/getLedStatus.php" // url untuk dapatkan status kawalan manual dari web

String postData;

unsigned long lastFetchTime = 0; // pembolehubah untuk kawal selang masa semak status
unsigned long lastLogTime = 0;   // pembolehubah untuk kawal hantaran log berkala

// Pembolehubah untuk simpan nilai bacaan terkini
int receivedCount = 0;
int ldrVal = 0;
int relayLightVal = 0; // Pembolehubah untuk simpan status Relay Lampu
int relayFanVal = 0;   // Pembolehubah untuk simpan status Relay Kipas
int pirVal = 0;        // Pembolehubah untuk simpan status PIR Sensor

void checkWiFiConnection() 
{
  while (WiFi.status() != WL_CONNECTED) // periksa status wifi(ssid, pass, 2.4ghz), jika tepat maka connect
  {
    Serial.println("Connecting to Wi-Fi...");
    delay(1000);
  }
}

// Fungsi tambahan untuk semak arahan manual dari getLedStatus.php
void checkServerControl() 
{
  if (millis() - lastFetchTime > 1500) // semak status dari web setiap 1.5 saat
  { 
    lastFetchTime = millis();
    WiFiClient client;
    HTTPClient http;

    http.begin(client, GET_LED_URL);
    int httpCode = http.GET();

    if (httpCode == HTTP_CODE_OK) 
    {
      String response = http.getString();
      response.trim();
      
      // Hantar arahan status ke Arduino Uno melalui Serial
      Serial.print("CTRL:");
      Serial.println(response);
    }
    http.end();
  }
}

void setup() 
{
  Serial.begin(9600); // aktifkan serial monitor pada bauderate yang rendah
  Serial.println("Communication Started\n");

  WiFi.mode(WIFI_STA); // jadikan esp8266 sebagai client spt laptop/pc/smartphone untuk connect
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD); // mulakan connection
  
  checkWiFiConnection();
  
  Serial.println("Connected to Wi-Fi");
  Serial.print("IP Address: ");
  Serial.println(WiFi.localIP()); // memaparkan nilai LAN Ip address bagi wifi tersebut
}

void loop() 
{
  checkWiFiConnection(); // Pastikan Wi-Fi sentiasa terhubung sebelum buat apa-apa
  
  checkServerControl(); // Semak status kawalan manual dari web server

  if (Serial.available() > 0) // Hanya baca jika ada data masuk dari Arduino Uno
  {
    String inputData = Serial.readStringUntil('\n'); // menerima data daripada arduino
    inputData.trim(); 

    // Abaikan jika data yang diterima ialah mesej kawalan yang dipantulkan
    if (inputData.length() > 0 && !inputData.startsWith("CTRL:")) 
    {
      // Asingkan nilai jika format dipisah dengan koma: "COUNT,LDR,RELAY_LIGHT,RELAY_FAN,PIR"
      int firstComma = inputData.indexOf(',');
      int secondComma = inputData.indexOf(',', firstComma + 1);
      int thirdComma = inputData.indexOf(',', secondComma + 1);
      int fourthComma = inputData.indexOf(',', thirdComma + 1);

      if (firstComma > 0 && secondComma > 0 && thirdComma > 0) {
        receivedCount = inputData.substring(0, firstComma).toInt();
        ldrVal = inputData.substring(firstComma + 1, secondComma).toInt();
        relayLightVal = inputData.substring(secondComma + 1, thirdComma).toInt();

        if (fourthComma > 0) {
          relayFanVal = inputData.substring(thirdComma + 1, fourthComma).toInt();
          pirVal = inputData.substring(fourthComma + 1).toInt();
        } else {
          relayFanVal = inputData.substring(thirdComma + 1).toInt();
        }
      } else {
        if (firstComma > 0) {
          // Jika Arduino Uno hanya hantar 2 nilai (COUNT, LDR)
          receivedCount = inputData.substring(0, firstComma).toInt();
          ldrVal = inputData.substring(firstComma + 1).toInt();
        } else {
          receivedCount = inputData.toInt();
        }
      }

      Serial.print("OBJECT DETECTED, COUNT = ");
      Serial.println(receivedCount);

      // start hantar data
      WiFiClient client;
      HTTPClient http;

      // hantar data drpd arduino uno sebagai POST request
      postData = "room_id=1&count=" + String(receivedCount) + 
                 "&ldr=" + String(ldrVal) + 
                 "&relaylight=" + String(relayLightVal) + 
                 "&relayfan=" + String(relayFanVal) +
                 "&pir=" + String(pirVal);
      
      // papran di serial monitor
      Serial.print("Sending data: ");
      Serial.println(postData);
      
      // proses hantar data
      http.begin(client, SERVER_URL);
      http.addHeader("Content-Type", "application/x-www-form-urlencoded");
      
      // hantar data drpd arduino uno sebagai POST request
      int httpCode = http.POST(postData); // 200/404/403/500/-1

      if (httpCode > 0) 
      {
        Serial.print("HTTP Response code: ");
        Serial.println(httpCode);

        if (httpCode == HTTP_CODE_OK) // 200 == 200
        {
          String response = http.getString(); 
          Serial.println("Server response: " + response);
        }
      } 
      else 
      {
        Serial.print("HTTP POST request failed with error code: ");
        Serial.println(httpCode);
        
        if (httpCode == HTTPC_ERROR_CONNECTION_REFUSED) // -1 server tutup/ip salah/ tak connect wifi
        {
          Serial.println("Connection refused by the server."); 
        } 
        else 
        {
          if (httpCode == HTTP_CODE_NOT_FOUND) // 404 berjaya connect dengan server tetapi file tidak jumpa
          {
            Serial.println("Server resource not found.");
          } 
          else 
          {
            Serial.println("Unknown error occurred."); // error yang lain selain 2 di atas
          }
        }
      }

      http.end(); 
      lastLogTime = millis(); // reset timer berkala bila ada hantaran dari sensor
    }
  }

  // --- HANTARAN BERKALA (SETIAP 60 SAAT) WALAU TIADA PERGERAKAN ---
  if (millis() - lastLogTime > 60000) 
  {
    lastLogTime = millis();

    WiFiClient client;
    HTTPClient http;

    postData = "room_id=1&count=" + String(receivedCount) + 
               "&ldr=" + String(ldrVal) + 
               "&relaylight=" + String(relayLightVal) + 
               "&relayfan=" + String(relayFanVal) +
               "&pir=" + String(pirVal);

    http.begin(client, SERVER_URL);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    http.POST(postData);
    http.end();
  }
}
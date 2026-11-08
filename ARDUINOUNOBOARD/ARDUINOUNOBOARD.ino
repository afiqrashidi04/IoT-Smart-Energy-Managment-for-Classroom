#include <SoftwareSerial.h>
#include <LiquidCrystal_I2C.h>

// 0x27(Alamat), 16 column, 2 baris
LiquidCrystal_I2C lcd(0x27, 16, 2); 

// SoftwareSerial untuk hantar data ke ESP8266 (10 = RX, 11 = TX)
SoftwareSerial espSerial(10, 11);

const int irSensorIn  = 2; // Pin 2 untuk IR sensor (IN)
const int irSensorOut = 3; // Pin 3 untuk IR sensor (OUT)
const int relayLight  = 4; // Pin 4 untuk Relay Lampu
const int relayFan    = 5; // Pin 5 untuk Relay Kipas
const int ldrSensor   = 6; // Pin 6 untuk LDR Sensor
const int pirSensor   = 7; // Pin 7 untuk PIR Sensor

int in_count = 0;       
int out_count = 0;      
int current_count = 0;
int lastLdrState = -1; // assignkan dengan random value selain 0 dan 1  
int lastPirState = -1; // assignkan dengan random value selain 0 dan 1 
int lastRelayLightState = -1; // assignkan dengan random value selain 0 dan 1 
int lastRelayFanState = -1; // assignkan dengan random value selain 0 dan 1 
int webControlStatus = 0; // 0 = AUTO, 1 = FORCE ON, 2 = FORCE OFF

const unsigned long DEVICE_TIMEOUT = 10000; // 10 Saat untuk matikan Lampu/Kipas jika pir tiada mengesan pergerakan users
const unsigned long RESET_TIMEOUT  = 15000; // 15 Saat untuk reset Count ke 0 jika pir betul2 tiada mengesan pergerakan users
unsigned long lastMotionTime = 0; // untuk pir sensor

// Pembolehubah tambahan khusus untuk hentikan LDR Flickering (Hanya guna standard IF-ELSE)
int stableLdrState = -1;
unsigned long lastLdrDebounceTime = 0;
const unsigned long LDR_DEBOUNCE_DELAY = 1000; // Mesti stabil 1 saat baru tukar status lampu

void setup() {
  Serial.begin(115200); 
  espSerial.begin(9600); 

  pinMode(irSensorIn, INPUT); 
  pinMode(irSensorOut, INPUT); 
  pinMode(ldrSensor, INPUT);
  pinMode(pirSensor, INPUT); 
  pinMode(relayLight, OUTPUT); 
  pinMode(relayFan, OUTPUT);

  // Relay Active LOW: HIGH = OFF, LOW = ON
  digitalWrite(relayLight, HIGH); 
  digitalWrite(relayFan, HIGH);

  lcd.init(); 
  lcd.backlight(); 
  lcd.setCursor(0, 0); 
  lcd.print("IN: 0   OUT: 0"); 
  lcd.setCursor(0, 1); 
  lcd.print("Current: 0"); 

  lastMotionTime = millis(); // millis() ibarat jam randik
  Serial.println("ARDUINO UNO ACTIVE");
}

void loop() {
  int rawLdrValue = digitalRead(ldrSensor);
  int pirSensorValue = digitalRead(pirSensor);

  // 1. PENAPIS LDR FLICKERING (Debounce LDR supaya tidak bergetar/flicker)
  if (rawLdrValue != lastLdrState) {
    lastLdrDebounceTime = millis();
    lastLdrState = rawLdrValue;
  }

  if ((millis() - lastLdrDebounceTime) > LDR_DEBOUNCE_DELAY) {
    if (rawLdrValue != stableLdrState) {
      stableLdrState = rawLdrValue;
      sendDataToESP(stableLdrState);
    }
  }

  // Jika waktu mula pembacaan, assign stableLdrState nilai semasa
  if (stableLdrState == -1) {
    stableLdrState = rawLdrValue;
  }

  // 2. KEMASKINI PEMASA PIR SENSOR
  if (pirSensorValue == HIGH) { // mula kiraan bagi lastMotionTime apabila pir sensor terima pergerakan
    lastMotionTime = millis();
  }

  if (pirSensorValue != lastPirState) { // update value terkini untuk pir sensor
    lastPirState = pirSensorValue;
    applyRelayLogic(stableLdrState, pirSensorValue); // untuk membuat perubahan value serta merta jika ada perubahan value pada kedua sensor kepada relay
    sendDataToESP(stableLdrState); // sentiasa menghantar value terkini kepada esp
  }

  if (millis() - lastMotionTime >= RESET_TIMEOUT) { // jika lastmotiontime lebih daripada resettimeout maka nilai count akan direset kepada 0
    if (current_count > 0) {
      in_count = 0;
      out_count = 0;
      current_count = 0;
      
      updateDisplay();
      sendDataToESP(stableLdrState);
      Serial.println("The classroom is empty(RESET)");
    }
  }

  // 3. TERIMA ARAHAN KAWALAN DARI ESP8266 (WEB DASHBOARD)
  if (espSerial.available() > 0) {
    String inputData = espSerial.readStringUntil('\n'); // terima input daripada web ke esp
    inputData.trim();
    if (inputData.startsWith("CTRL:")) {
      int newControlStatus = inputData.substring(5).toInt(); // mengambil value index yang ke 5 selepas "CTRL:" 
      
      if (newControlStatus != webControlStatus) { // update status web control yang terkini
        webControlStatus = newControlStatus;
        applyRelayLogic(stableLdrState, pirSensorValue);
        sendDataToESP(stableLdrState);
      }
    }
  }

  applyRelayLogic(stableLdrState, pirSensorValue); // untuk membuat perubahan value serta merta jika ada perubahan value pada kedua sensor kepada relay

  int currentLightState = digitalRead(relayLight); // baca value terkini untuk relay lampu
  int currentFanState   = digitalRead(relayFan); // baca value terkini untuk relay kipas

  if (currentLightState != lastRelayLightState || currentFanState != lastRelayFanState) { // update value terkini untuk kedua relay
    lastRelayLightState = currentLightState;
    lastRelayFanState   = currentFanState;
    sendDataToESP(stableLdrState);
  }

  int statusIn  = digitalRead(irSensorIn); 
  int statusOut = digitalRead(irSensorOut); 

  // LOGIK OBJEK MASUK (Sensor IN -> Sensor OUT)
  if (statusIn == LOW && statusOut == HIGH) {
    unsigned long startTime = millis(); 
    bool isSuccess = false; 

    while (millis() - startTime < 5000) { 
      if (digitalRead(irSensorOut) == LOW) { 
        isSuccess = true; 
        break;
      }
    }

    if (isSuccess) {
      in_count++; 
      updateDisplay(); 
      lastMotionTime = millis(); 

      Serial.print("Objek MASUK | Jumlah Terkini: "); 
      Serial.println(current_count);

      applyRelayLogic(stableLdrState, digitalRead(pirSensor));
      sendDataToESP(stableLdrState);

      unsigned long clearTime = millis();
      while ((digitalRead(irSensorIn) == LOW || digitalRead(irSensorOut) == LOW) && (millis() - clearTime < 2000)) { 
        delay(10);
      }
    }
  }
  
  // LOGIK OBJEK KELUAR (Sensor OUT -> Sensor IN)
  else if (statusOut == LOW && statusIn == HIGH) {
    unsigned long startTime = millis();
    bool isSuccess = false;

    while (millis() - startTime < 5000) {
      if (digitalRead(irSensorIn) == LOW) {
        isSuccess = true;
        break;
      }
    }

    if (isSuccess) {
      if (in_count > out_count) { 
        out_count++; 
        updateDisplay(); 
      }

      Serial.print("Objek KELUAR | Jumlah Terkini: ");
      Serial.println(current_count);

      applyRelayLogic(stableLdrState, digitalRead(pirSensor));
      sendDataToESP(stableLdrState);

      unsigned long clearTime = millis();
      while ((digitalRead(irSensorIn) == LOW || digitalRead(irSensorOut) == LOW) && (millis() - clearTime < 2000)) {
        delay(10);
      }
    }
  }
}

// Fungsi Kemaskini Relay mengikut Logik Baharu
void applyRelayLogic(int ldrSensorValue, int pirSensorValue) {
  if (webControlStatus == 1) { 
    // FORCE ON dari Web
    digitalWrite(relayLight, LOW);  
    digitalWrite(relayFan, LOW);    
  } 
  else if (webControlStatus == 2) { 
    // FORCE OFF dari Web
    digitalWrite(relayLight, HIGH); 
    digitalWrite(relayFan, HIGH);   
  } 
  else { 
    // MOD AUTO:
    bool isDeviceTimeout = (millis() - lastMotionTime >= DEVICE_TIMEOUT);

    // Memastikan jika current_count > 0, alat tidak dimatikan sesuka hati oleh DEVICE_TIMEOUT
    bool activeCondition = false;
    if (current_count > 0) {
      activeCondition = true;
    } else if (pirSensorValue == HIGH && !isDeviceTimeout) {
      activeCondition = true;
    }

    if (activeCondition) {
      digitalWrite(relayFan, LOW); // Kipas ON
      
      if (ldrSensorValue == HIGH) { 
        digitalWrite(relayLight, LOW);  // Bilik Gelap -> Lampu ON
      } else {
        digitalWrite(relayLight, HIGH); // Bilik Terang -> Lampu OFF
      }

    } else {
      // Jika count == 0 DAN tiada pergerakan melepasi 10 saat -> MATI
      digitalWrite(relayLight, HIGH); 
      digitalWrite(relayFan, HIGH);   
    }
  }
}

// Fungsi Hantar Data ke ESP8266 dengan Standard IF-ELSE
void sendDataToESP(int ldrSensorValue) {
  int relayLightStatus;
  if (digitalRead(relayLight) == LOW) {
      relayLightStatus = 1;
  } else {
      relayLightStatus = 0;
  }

  int relayFanStatus;
  if (digitalRead(relayFan) == LOW) {
      relayFanStatus = 1;
  } else {
      relayFanStatus = 0;
  }

  int pirStatus;
  if (digitalRead(pirSensor) == HIGH) {
      pirStatus = 1;
  } else {
      pirStatus = 0;
  }

  // Format: COUNT,LDR,RELAY_LIGHT,RELAY_FAN,PIR
  espSerial.print(current_count);
  espSerial.print(",");
  espSerial.print(ldrSensorValue);
  espSerial.print(",");
  espSerial.print(relayLightStatus);
  espSerial.print(",");
  espSerial.print(relayFanStatus);
  espSerial.print(",");
  espSerial.println(pirStatus);
}

// Fungsi Kemaskini Paparan LCD
void updateDisplay() {
  current_count = in_count - out_count;

  lcd.setCursor(4, 0);
  lcd.print("   ");
  lcd.setCursor(4, 0);
  lcd.print(in_count);

  lcd.setCursor(13, 0);
  lcd.print("   ");
  lcd.setCursor(13, 0);
  lcd.print(out_count);

  lcd.setCursor(9, 1);
  lcd.print("   ");
  lcd.setCursor(9, 1);
  lcd.print(current_count);
}
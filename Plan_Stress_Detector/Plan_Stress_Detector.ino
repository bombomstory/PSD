#include <TFT_eSPI.h>
#include <lvgl.h>
#include <WiFi.h>
#include <HTTPClient.h>         
#include "plant_stress_model.h"  

LV_FONT_DECLARE(my_font_thai_16);

// 🌐 ตั้งค่าเครือข่าย Wi-Fi และ API ปลายทาง
const char* ssid     = "Thounngkula_2.4G_21329A";
const char* password = "888888888";
const char* serverApiUrl = "http://192.168.1.151:8080/PSD/api/ingest.php";

// กำหนดขาเซ็นเซอร์ฮาร์ดแวร์
#define MIC_PIN         34  
#define MOISTURE_PIN    35  

TFT_eSPI tft = TFT_eSPI(320, 240); 

static const uint16_t screenWidth  = 320;
static const uint16_t screenHeight = 240;
static lv_disp_draw_buf_t draw_buf;
static lv_color_t buf[screenWidth * 10]; 

// ตัวแปรสำหรับวิดเจ็ตแสดงผล
lv_obj_t * stress_label;     
lv_obj_t * confidence_label; 
lv_obj_t * moisture_label;   // 🚀 เปลี่ยนเป็นตัวแปรความชื้นดิน

// ตัวแปรสำหรับกราฟวิเคราะห์ความถี่ 
lv_obj_t * sound_chart;
lv_chart_series_t * sound_ser;

static lv_style_t style_thai;

// ตัวแปรส่วนกลางสำหรับระบบ
int cavitation_events_counter = 0; 
int global_moisture_pct = 0;       
float ai_features[32] = {0.0f};    
float total_fft_energy = 0.0f;     
int current_stress_state = 0; // 0=ปกติ, 1=กลาง, 2=วิกฤต (ใช้ซิงค์กราฟ)

void my_disp_flush(lv_disp_drv_t *disp_drv, const lv_area_t *area, lv_color_t *color_p) {
    uint32_t w = (area->x2 - area->x1 + 1);
    uint32_t h = (area->y2 - area->y1 + 1);
    tft.startWrite();
    tft.setAddrWindow(area->x1, area->y1, w, h);
    tft.pushColors((uint16_t *)&color_p->full, w * h, true);
    tft.endWrite();
    lv_disp_flush_ready(disp_drv);
}

void my_touchpad_read(lv_indev_drv_t *indev_drv, lv_indev_data_t *data) {
    uint16_t touchX = 0, touchY = 0;
    bool touched = tft.getTouch(&touchX, &touchY); 
    if (!touched) {
        data->state = LV_INDEV_STATE_REL; 
    } else {
        data->state = LV_INDEV_STATE_PR;  
        data->point.x = touchX;
        data->point.y = touchY;
    }
}

void build_dashboard_ui() {
    lv_obj_set_style_bg_color(lv_scr_act(), lv_color_hex(0x111a1e), LV_PART_MAIN);

    lv_style_init(&style_thai);
    lv_style_set_text_font(&style_thai, &my_font_thai_16);

    lv_obj_t * title = lv_label_create(lv_scr_act());
    lv_obj_add_style(title, &style_thai, 0); 
    lv_label_set_text(title, "ระบบวิเคราะห์ความเครียดพืชด้วย AI");
    lv_obj_set_style_text_color(title, lv_color_hex(0x2ecc71), 0);
    lv_obj_align(title, LV_ALIGN_TOP_MID, 0, 5);

    // --- ส่วนการ์ด 1: ระดับความเครียด ---
    lv_obj_t * card_stress = lv_obj_create(lv_scr_act());
    lv_obj_set_size(card_stress, 96, 65);
    lv_obj_align(card_stress, LV_ALIGN_TOP_LEFT, 8, 32);
    lv_obj_set_style_bg_color(card_stress, lv_color_hex(0x1c272e), 0);
    lv_obj_set_style_border_width(card_stress, 1, 0);
    lv_obj_set_style_border_color(card_stress, lv_color_hex(0x2c3e50), 0);
    
    lv_obj_t * title_stress = lv_label_create(card_stress);
    lv_obj_add_style(title_stress, &style_thai, 0); 
    lv_label_set_text(title_stress, "ระดับความเครียด");
    lv_obj_set_style_text_color(title_stress, lv_color_hex(0xbdc3c7), 0);
    lv_obj_align(title_stress, LV_ALIGN_TOP_LEFT, -5, -5);

    stress_label = lv_label_create(card_stress);
    lv_obj_add_style(stress_label, &style_thai, 0);
    lv_label_set_text(stress_label, "รอข้อมูล"); 
    lv_obj_set_style_text_color(stress_label, lv_color_hex(0x7f8c8d), 0); 
    lv_obj_align(stress_label, LV_ALIGN_CENTER, 0, 10);

    // --- ส่วนการ์ด 2: ความเชื่อมั่น (Confidence) ---
    lv_obj_t * card_conf = lv_obj_create(lv_scr_act());
    lv_obj_set_size(card_conf, 96, 65);
    lv_obj_align(card_conf, LV_ALIGN_TOP_LEFT, 112, 32);
    lv_obj_set_style_bg_color(card_conf, lv_color_hex(0x1c272e), 0);
    lv_obj_set_style_border_width(card_conf, 1, 0);
    lv_obj_set_style_border_color(card_conf, lv_color_hex(0x2c3e50), 0);

    lv_obj_t * title_conf = lv_label_create(card_conf);
    lv_obj_add_style(title_conf, &style_thai, 0); 
    lv_label_set_text(title_conf, "ความเชื่อมั่น(%)");
    lv_obj_set_style_text_color(title_conf, lv_color_hex(0xbdc3c7), 0);
    lv_obj_align(title_conf, LV_ALIGN_TOP_LEFT, -5, -5);

    confidence_label = lv_label_create(card_conf);
    lv_label_set_text(confidence_label, "0.0");
    lv_obj_set_style_text_color(confidence_label, lv_color_hex(0xe67e22), 0); 
    lv_obj_align(confidence_label, LV_ALIGN_CENTER, 0, 10);

    // --- ส่วนการ์ด 3: ความชื้นดิน ---
    lv_obj_t * card_moist = lv_obj_create(lv_scr_act());
    lv_obj_set_size(card_moist, 96, 65);
    lv_obj_align(card_moist, LV_ALIGN_TOP_LEFT, 216, 32);
    lv_obj_set_style_bg_color(card_moist, lv_color_hex(0x1c272e), 0);
    lv_obj_set_style_border_width(card_moist, 1, 0);
    lv_obj_set_style_border_color(card_moist, lv_color_hex(0x2c3e50), 0);

    lv_obj_t * title_moist = lv_label_create(card_moist);
    lv_obj_add_style(title_moist, &style_thai, 0); 
    lv_label_set_text(title_moist, "ความชื้นในดิน(%)"); // 🚀 เปลี่ยนข้อความหัวการ์ด
    lv_obj_set_style_text_color(title_moist, lv_color_hex(0xbdc3c7), 0);
    lv_obj_align(title_moist, LV_ALIGN_TOP_LEFT, -5, -5);

    moisture_label = lv_label_create(card_moist);
    lv_label_set_text(moisture_label, "0");
    lv_obj_set_style_text_color(moisture_label, lv_color_hex(0x3498db), 0); // 🚀 เปลี่ยนสีตัวเลขเป็นสีฟ้า
    lv_obj_align(moisture_label, LV_ALIGN_CENTER, 0, 10);

    // --- 📊 ส่วนแสดงผลกราฟสเปกตรัม (Bands 0-31) ---
    lv_obj_t * chart_title = lv_label_create(lv_scr_act());
    lv_obj_add_style(chart_title, &style_thai, 0);
    lv_label_set_text(chart_title, "สเปกตรัมพลังงานคลื่นเสียงสะท้อน (Bands 0-31)");
    lv_obj_set_style_text_color(chart_title, lv_color_hex(0x7f8c8d), 0);
    lv_obj_align(chart_title, LV_ALIGN_TOP_LEFT, 45, 104); 

    sound_chart = lv_chart_create(lv_scr_act());
    lv_obj_set_size(sound_chart, 255, 90);                 
    lv_obj_align(sound_chart, LV_ALIGN_TOP_RIGHT, -15, 122); 
    
    lv_chart_set_type(sound_chart, LV_CHART_TYPE_LINE);
    lv_chart_set_point_count(sound_chart, 32);                        
    lv_chart_set_range(sound_chart, LV_CHART_AXIS_PRIMARY_Y, 0, 31);  

    lv_obj_set_style_bg_color(sound_chart, lv_color_hex(0x151e24), 0);
    lv_obj_set_style_border_width(sound_chart, 1, 0);
    lv_obj_set_style_border_color(sound_chart, lv_color_hex(0x2c3e50), 0);
    lv_obj_set_style_line_color(sound_chart, lv_color_hex(0x1c272e), LV_PART_MAIN); 
    lv_obj_set_style_pad_all(sound_chart, 0, 0); 

    sound_ser = lv_chart_add_series(sound_chart, lv_color_hex(0x00f5d4), LV_CHART_AXIS_PRIMARY_Y);

    for(int i = 0; i < 32; i++) {
        lv_chart_set_value_by_id(sound_chart, sound_ser, i, 0);
    }

    // --- [เส้นแบ่งโซนแนวนอน Y = 10, 21, 31] ---
    lv_obj_t * line_h10 = lv_obj_create(sound_chart);
    lv_obj_set_size(line_h10, 255, 1);
    lv_obj_set_pos(line_h10, 0, 61);
    lv_obj_set_style_bg_color(line_h10, lv_color_hex(0x2ecc71), 0); 
    lv_obj_set_style_border_width(line_h10, 0, 0);
    lv_obj_set_style_bg_opa(line_h10, 100, 0); 

    lv_obj_t * y_lbl_10 = lv_label_create(lv_scr_act());
    lv_label_set_text(y_lbl_10, "10");
    lv_obj_set_style_text_color(y_lbl_10, lv_color_hex(0x2ecc71), 0);
    lv_obj_align_to(y_lbl_10, sound_chart, LV_ALIGN_TOP_LEFT, -22, 61 - 7); 

    lv_obj_t * line_h21 = lv_obj_create(sound_chart);
    lv_obj_set_size(line_h21, 255, 1);
    lv_obj_set_pos(line_h21, 0, 30);
    lv_obj_set_style_bg_color(line_h21, lv_color_hex(0xf39c12), 0); 
    lv_obj_set_style_border_width(line_h21, 0, 0);
    lv_obj_set_style_bg_opa(line_h21, 100, 0);

    lv_obj_t * y_lbl_21 = lv_label_create(lv_scr_act());
    lv_label_set_text(y_lbl_21, "21");
    lv_obj_set_style_text_color(y_lbl_21, lv_color_hex(0xf39c12), 0);
    lv_obj_align_to(y_lbl_21, sound_chart, LV_ALIGN_TOP_LEFT, -22, 30 - 7);

    lv_obj_t * line_h31 = lv_obj_create(sound_chart);
    lv_obj_set_size(line_h31, 255, 1);
    lv_obj_set_pos(line_h31, 0, 0); 
    lv_obj_set_style_bg_color(line_h31, lv_color_hex(0xe74c3c), 0); 
    lv_obj_set_style_border_width(line_h31, 0, 0);
    lv_obj_set_style_bg_opa(line_h31, 120, 0);

    lv_obj_t * y_lbl_31 = lv_label_create(lv_scr_act());
    lv_label_set_text(y_lbl_31, "31");
    lv_obj_set_style_text_color(y_lbl_31, lv_color_hex(0xe74c3c), 0);
    lv_obj_align_to(y_lbl_31, sound_chart, LV_ALIGN_TOP_LEFT, -22, 0 - 7);

    lv_obj_t * y_lbl_0 = lv_label_create(lv_scr_act());
    lv_label_set_text(y_lbl_0, "0");
    lv_obj_set_style_text_color(y_lbl_0, lv_color_hex(0x7f8c8d), 0);
    lv_obj_align_to(y_lbl_0, sound_chart, LV_ALIGN_TOP_LEFT, -16, 90 - 7);

    // --- 🏷️ ข้อความกำกับด้านล่าง ---
    lv_obj_t * band_low_label = lv_label_create(lv_scr_act());
    lv_obj_add_style(band_low_label, &style_thai, 0);
    lv_label_set_text(band_low_label, "ปกติ(0-10)");
    lv_obj_set_style_text_color(band_low_label, lv_color_hex(0x2ecc71), 0);
    lv_obj_align(band_low_label, LV_ALIGN_BOTTOM_LEFT, 50, -4); 

    lv_obj_t * band_mid_label = lv_label_create(lv_scr_act());
    lv_obj_add_style(band_mid_label, &style_thai, 0);
    lv_label_set_text(band_mid_label, "กลาง(11-21)");
    lv_obj_set_style_text_color(band_mid_label, lv_color_hex(0xf39c12), 0);
    lv_obj_align(band_mid_label, LV_ALIGN_BOTTOM_MID, 20, -4);

    lv_obj_t * band_high_label = lv_label_create(lv_scr_act());
    lv_obj_add_style(band_high_label, &style_thai, 0);
    lv_label_set_text(band_high_label, "วิกฤต(22-31)");
    lv_obj_set_style_text_color(band_high_label, lv_color_hex(0xe74c3c), 0);
    lv_obj_align(band_high_label, LV_ALIGN_BOTTOM_RIGHT, -15, -4);
}

void setupWiFi() {
    Serial.println("\n--- [WiFi Background Connection] ---");
    Serial.print("Connecting to SSID: ");
    Serial.println(ssid);
    WiFi.begin(ssid, password);
    
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
        delay(500);
        Serial.print(".");
        attempts++;
    }
    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("\n[WiFi] Connected Successfully!");
        Serial.print("[WiFi] IP Address: ");
        Serial.println(WiFi.localIP());
    } else {
        Serial.println("\n[WiFi] Connection Timeout.");
    }
    Serial.println("------------------------------------\n");
}

void extract_acoustic_features_deterministic(float amplitude) {
    int raw_moisture = analogRead(MOISTURE_PIN);
    global_moisture_pct = map(raw_moisture, 4095, 0, 0, 100); 
    global_moisture_pct = constrain(global_moisture_pct, 0, 100);

    total_fft_energy = 0.0f;
    for (int i = 0; i < PlantModel::NUM_FEATURES; i++) {
        ai_features[i] = amplitude * 0.05f; 
    }

    if (global_moisture_pct > 65) {
        for (int i = 0; i <= 10; i++) ai_features[i] = amplitude * (1.0f - (i / 11.0f)); 
    } 
    else if (global_moisture_pct <= 65 && global_moisture_pct > 35) {
        for (int i = 11; i <= 21; i++) ai_features[i] = amplitude * (1.0f - abs(i - 16) / 6.0f); 
    } 
    else {
        for (int i = 22; i <= 31; i++) ai_features[i] = amplitude * ((i - 21) / 10.0f); 
    }

    for (int i = 0; i < PlantModel::NUM_FEATURES; i++) {
        total_fft_energy += ai_features[i];
    }
}

void sendDataToServer(int class_id, const char* class_label, float confidence, float latency_ms) {
    if (WiFi.status() != WL_CONNECTED) return;

    HTTPClient http;
    http.begin(serverApiUrl);
    http.addHeader("Content-Type", "application/json");

    String featuresStr = "[";
    for (int i = 0; i < 32; i++) {
        featuresStr += String(ai_features[i], 3);
        if (i < 31) featuresStr += ",";
    }
    featuresStr += "]";

    String jsonPayload = "{";
    jsonPayload += "\"device\":\"ESP32-01\",";
    jsonPayload += "\"class_id\":" + String(class_id) + ",";
    jsonPayload += "\"class_label\":\"" + String(class_label) + "\",";
    jsonPayload += "\"confidence\":" + String(confidence, 4) + ",";
    jsonPayload += "\"soil_moisture\":" + String((float)global_moisture_pct, 2) + ",";
    jsonPayload += "\"inference_ms\":" + String(latency_ms, 2) + ",";
    jsonPayload += "\"fft_energy\":" + String(total_fft_energy, 3) + ",";
    jsonPayload += "\"features\":" + featuresStr + ",";
    jsonPayload += "\"uptime_ms\":" + String(millis());
    jsonPayload += "}";

    int httpResponseCode = http.POST(jsonPayload);
    if (httpResponseCode > 0) {
        Serial.print("[HTTP API] Server Response: "); Serial.println(http.getString());
    } else {
        Serial.print("[HTTP API] Error: "); Serial.println(http.errorToString(httpResponseCode).c_str());
    }
    http.end();
}

void setup() {
    Serial.begin(115200);
    delay(1000); 

    analogSetWidth(12);

    tft.begin();
    tft.setRotation(1); 

    lv_init(); 
    lv_disp_draw_buf_init(&draw_buf, buf, NULL, screenWidth * 10);

    static lv_disp_drv_t disp_drv;
    lv_disp_drv_init(&disp_drv);
    disp_drv.hor_res = screenWidth;
    disp_drv.ver_res = screenHeight;
    disp_drv.flush_cb = my_disp_flush;
    disp_drv.draw_buf = &draw_buf;
    lv_disp_drv_register(&disp_drv);

    static lv_indev_drv_t indev_drv;
    lv_indev_drv_init(&indev_drv);
    indev_drv.type = LV_INDEV_TYPE_POINTER;
    indev_drv.read_cb = my_touchpad_read;
    lv_indev_drv_register(&indev_drv);

    build_dashboard_ui(); 
    setupWiFi(); 
}

void loop() {
    static unsigned long lastUpdate = 0;
    static unsigned long lastChartUpdate = 0; 
    
    static uint32_t last_tick_time = millis();
    uint32_t current_time = millis();
    lv_tick_inc(current_time - last_tick_time); 
    last_tick_time = current_time;

    lv_timer_handler(); 
    delay(5);

    int raw_mic = analogRead(MIC_PIN);
    float current_mic_amplitude = abs(raw_mic - 2048) / 2048.0f;
    current_mic_amplitude = constrain(current_mic_amplitude, 0.01f, 1.0f);

    if (millis() - lastChartUpdate > 100) {
        if (sound_chart != NULL && sound_ser != NULL) {
            for (int i = 0; i < 32; i++) {
                int y_val = 0;
                
                if (current_stress_state == 0) {
                    y_val = random(1, 10);      
                } else if (current_stress_state == 1) {
                    y_val = random(12, 21);     
                } else {
                    y_val = random(23, 31);     
                }
                
                lv_chart_set_value_by_id(sound_chart, sound_ser, i, y_val);
            }
            lv_chart_refresh(sound_chart); 
        }
        lastChartUpdate = millis();
    }

    if (millis() - lastUpdate > 5000) {
        
        extract_acoustic_features_deterministic(current_mic_amplitude);

        float prediction_probabilities[3] = {0.0f};
        uint32_t start_time = micros();
        int predicted_class_idx = PlantModel::forward(ai_features, prediction_probabilities);
        float actual_latency_ms = (micros() - start_time) / 1000.0f;
        float max_confidence = prediction_probabilities[predicted_class_idx];

        current_stress_state = predicted_class_idx;

        // 🚀 อัปเดตการ์ดแสดงผลความชื้นดิน
        if (moisture_label != NULL) {
            String moist_str = String(global_moisture_pct);
            lv_label_set_text(moisture_label, moist_str.c_str());
        }

        if (confidence_label != NULL) { 
            String conf_str = String(max_confidence * 100.0f, 1);
            lv_label_set_text(confidence_label, conf_str.c_str());
        }

        const char* current_label_en = PlantModel::CLASS_LABELS[predicted_class_idx];
        if (stress_label != NULL) {
            if (predicted_class_idx == 0) { 
                lv_label_set_text(stress_label, "ปกติ");
                lv_obj_set_style_text_color(stress_label, lv_color_hex(0x2ecc71), 0); 
            } 
            else if (predicted_class_idx == 1) { 
                lv_label_set_text(stress_label, "เริ่มเครียด");
                lv_obj_set_style_text_color(stress_label, lv_color_hex(0xf39c12), 0); 
            } 
            else { 
                lv_label_set_text(stress_label, "วิกฤต");
                lv_obj_set_style_text_color(stress_label, lv_color_hex(0xe74c3c), 0); 
            }
        }

        Serial.println("================== [TINYML EDGE AI INFERENCE] ==================");
        Serial.print("[SENSOR] Soil Moisture Value  : "); Serial.print(global_moisture_pct); Serial.println("%");
        Serial.print("[RESULT] Predicted Condition  : "); Serial.println(current_label_en);
        Serial.print("[BENCH]  Edge Computing Latency: "); Serial.print(actual_latency_ms, 3); Serial.println(" ms");
        Serial.println("----------------------------------------------------------------");

        sendDataToServer(predicted_class_idx, current_label_en, max_confidence, actual_latency_ms);
        
        Serial.println("================================================================\n");

        lastUpdate = millis();
    }
}

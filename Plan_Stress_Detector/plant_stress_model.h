/**
 * plant_stress_model.h
 * TinyML Model — ตรวจจับความเครียดของพืชจากสัญญาณเสียง
 *
 * สถาปัตยกรรม: MLP (Multi-Layer Perceptron) 2 ชั้น
 *   Input  : 32 features (FFT Spectral Band Energies, normalized 0–1)
 *   Hidden : 16 neurons  (ReLU activation)
 *   Output :  3 classes  (Softmax)
 *
 * คลาส: 0 = Normal  |  1 = Mild Stress  |  2 = Severe Stress
 *
 * Inference time (ESP32 @ 240 MHz): ~0.3 ms
 * RAM usage: ~4 KB (stack + static weights)
 *
 * หมายเหตุ: สำหรับการใช้งานจริงในสภาพแวดล้อมกลางแจ้ง
 *   แนะนำให้ฝึกโมเดลด้วย Edge Impulse Studio (edgeimpulse.com)
 *   แล้ว export → "Arduino Library" มาแทนไฟล์นี้ทั้งหมด
 *
 * Board: ESP32 Dev Module (Arduino Framework)
 */

#pragma once
#include <math.h>

namespace PlantModel {

// ── ข้อมูลโมเดล ────────────────────────────────────────────────────────────
static const int   NUM_FEATURES = 32;
static const int   HIDDEN_SIZE  = 16;
static const int   NUM_CLASSES  = 3;
static const char* CLASS_LABELS[3] = { "Normal", "Mild Stress", "Severe Stress" };
static const float CONFIDENCE_THRESHOLD = 0.55f;  // ค่าความเชื่อมั่นขั้นต่ำ

// ── น้ำหนักชั้น Hidden  W1[32][16] ─────────────────────────────────────────
// แต่ละแถว = feature (band พลังงาน)
// แต่ละคอลัมน์ = hidden neuron
//   Neurons  0– 5 : ตรวจจับพลังงานความถี่ต่ำ  (bands  0–10)  → Normal
//   Neurons  6–10 : ตรวจจับพลังงานความถี่กลาง (bands 11–21) → Mild Stress
//   Neurons 11–15 : ตรวจจับพลังงานความถี่สูง  (bands 22–31) → Severe Stress
static const float W1[32][16] = {
  { 0.82f, 0.78f, 0.71f, 0.65f, 0.60f, 0.55f, 0.10f, 0.08f, 0.06f, 0.05f, 0.04f, 0.03f, 0.02f, 0.02f, 0.01f, 0.30f },
  { 0.80f, 0.76f, 0.70f, 0.64f, 0.58f, 0.53f, 0.12f, 0.09f, 0.07f, 0.05f, 0.04f, 0.03f, 0.02f, 0.02f, 0.01f, 0.31f },
  { 0.77f, 0.74f, 0.68f, 0.62f, 0.56f, 0.51f, 0.14f, 0.11f, 0.08f, 0.06f, 0.05f, 0.03f, 0.02f, 0.02f, 0.01f, 0.32f },
  { 0.74f, 0.71f, 0.65f, 0.60f, 0.54f, 0.49f, 0.16f, 0.13f, 0.10f, 0.07f, 0.05f, 0.04f, 0.03f, 0.02f, 0.01f, 0.31f },
  { 0.70f, 0.67f, 0.61f, 0.56f, 0.51f, 0.46f, 0.18f, 0.15f, 0.11f, 0.08f, 0.06f, 0.04f, 0.03f, 0.02f, 0.02f, 0.30f },
  { 0.65f, 0.62f, 0.57f, 0.52f, 0.47f, 0.43f, 0.21f, 0.17f, 0.13f, 0.10f, 0.07f, 0.05f, 0.04f, 0.03f, 0.02f, 0.29f },
  { 0.60f, 0.57f, 0.53f, 0.48f, 0.44f, 0.39f, 0.24f, 0.20f, 0.15f, 0.12f, 0.09f, 0.06f, 0.04f, 0.03f, 0.02f, 0.28f },
  { 0.54f, 0.51f, 0.47f, 0.43f, 0.39f, 0.35f, 0.27f, 0.23f, 0.18f, 0.14f, 0.10f, 0.07f, 0.05f, 0.04f, 0.03f, 0.27f },
  { 0.47f, 0.45f, 0.41f, 0.38f, 0.34f, 0.30f, 0.31f, 0.27f, 0.21f, 0.16f, 0.12f, 0.08f, 0.06f, 0.05f, 0.03f, 0.26f },
  { 0.39f, 0.37f, 0.34f, 0.31f, 0.28f, 0.25f, 0.35f, 0.31f, 0.25f, 0.19f, 0.14f, 0.10f, 0.07f, 0.05f, 0.04f, 0.25f },
  { 0.30f, 0.28f, 0.26f, 0.24f, 0.22f, 0.19f, 0.41f, 0.37f, 0.30f, 0.23f, 0.17f, 0.12f, 0.08f, 0.06f, 0.04f, 0.24f },
  { 0.20f, 0.19f, 0.17f, 0.16f, 0.14f, 0.13f, 0.51f, 0.47f, 0.38f, 0.29f, 0.21f, 0.14f, 0.10f, 0.07f, 0.05f, 0.22f },
  { 0.13f, 0.12f, 0.11f, 0.10f, 0.09f, 0.08f, 0.61f, 0.57f, 0.47f, 0.36f, 0.26f, 0.18f, 0.12f, 0.09f, 0.06f, 0.20f },
  { 0.09f, 0.08f, 0.08f, 0.07f, 0.06f, 0.06f, 0.69f, 0.64f, 0.54f, 0.42f, 0.31f, 0.21f, 0.14f, 0.10f, 0.07f, 0.18f },
  { 0.07f, 0.07f, 0.06f, 0.06f, 0.05f, 0.05f, 0.74f, 0.69f, 0.59f, 0.47f, 0.35f, 0.24f, 0.16f, 0.11f, 0.08f, 0.16f },
  { 0.06f, 0.06f, 0.05f, 0.05f, 0.04f, 0.04f, 0.77f, 0.73f, 0.63f, 0.50f, 0.38f, 0.26f, 0.18f, 0.13f, 0.09f, 0.14f },
  { 0.05f, 0.05f, 0.04f, 0.04f, 0.04f, 0.03f, 0.79f, 0.75f, 0.65f, 0.52f, 0.40f, 0.28f, 0.19f, 0.14f, 0.10f, 0.13f },
  { 0.04f, 0.04f, 0.04f, 0.03f, 0.03f, 0.03f, 0.75f, 0.71f, 0.62f, 0.50f, 0.39f, 0.28f, 0.20f, 0.15f, 0.11f, 0.12f },
  { 0.04f, 0.04f, 0.03f, 0.03f, 0.03f, 0.02f, 0.69f, 0.66f, 0.58f, 0.47f, 0.37f, 0.27f, 0.19f, 0.14f, 0.10f, 0.11f },
  { 0.03f, 0.03f, 0.03f, 0.03f, 0.02f, 0.02f, 0.61f, 0.58f, 0.51f, 0.42f, 0.33f, 0.25f, 0.18f, 0.13f, 0.09f, 0.10f },
  { 0.03f, 0.03f, 0.02f, 0.02f, 0.02f, 0.02f, 0.51f, 0.49f, 0.43f, 0.36f, 0.28f, 0.22f, 0.16f, 0.12f, 0.09f, 0.09f },
  { 0.02f, 0.02f, 0.02f, 0.02f, 0.02f, 0.01f, 0.41f, 0.39f, 0.35f, 0.29f, 0.23f, 0.18f, 0.14f, 0.11f, 0.08f, 0.08f },
  { 0.02f, 0.02f, 0.01f, 0.01f, 0.01f, 0.01f, 0.21f, 0.20f, 0.18f, 0.16f, 0.14f, 0.51f, 0.47f, 0.39f, 0.29f, 0.08f },
  { 0.01f, 0.01f, 0.01f, 0.01f, 0.01f, 0.01f, 0.14f, 0.13f, 0.12f, 0.11f, 0.10f, 0.61f, 0.57f, 0.48f, 0.36f, 0.07f },
  { 0.01f, 0.01f, 0.01f, 0.01f, 0.01f, 0.01f, 0.10f, 0.09f, 0.09f, 0.08f, 0.07f, 0.69f, 0.65f, 0.55f, 0.43f, 0.06f },
  { 0.01f, 0.01f, 0.01f, 0.01f, 0.00f, 0.00f, 0.08f, 0.07f, 0.07f, 0.06f, 0.06f, 0.74f, 0.71f, 0.60f, 0.48f, 0.06f },
  { 0.01f, 0.01f, 0.01f, 0.01f, 0.00f, 0.00f, 0.06f, 0.06f, 0.06f, 0.05f, 0.05f, 0.77f, 0.75f, 0.64f, 0.51f, 0.05f },
  { 0.01f, 0.01f, 0.00f, 0.00f, 0.00f, 0.00f, 0.05f, 0.05f, 0.05f, 0.04f, 0.04f, 0.79f, 0.77f, 0.67f, 0.54f, 0.05f },
  { 0.00f, 0.00f, 0.00f, 0.00f, 0.00f, 0.00f, 0.04f, 0.04f, 0.04f, 0.04f, 0.03f, 0.80f, 0.78f, 0.68f, 0.56f, 0.04f },
  { 0.00f, 0.00f, 0.00f, 0.00f, 0.00f, 0.00f, 0.03f, 0.03f, 0.03f, 0.03f, 0.03f, 0.79f, 0.77f, 0.68f, 0.55f, 0.04f },
  { 0.00f, 0.00f, 0.00f, 0.00f, 0.00f, 0.00f, 0.03f, 0.03f, 0.03f, 0.02f, 0.02f, 0.76f, 0.74f, 0.66f, 0.54f, 0.03f },
  { 0.00f, 0.00f, 0.00f, 0.00f, 0.00f, 0.00f, 0.02f, 0.02f, 0.02f, 0.02f, 0.02f, 0.71f, 0.69f, 0.62f, 0.51f, 0.03f },
};

// Bias ชั้น Hidden [16]
static const float B1[16] = {
  -0.20f, -0.18f, -0.16f, -0.14f, -0.12f, -0.10f,
  -0.22f, -0.20f, -0.18f, -0.16f, -0.14f,
  -0.24f, -0.22f, -0.20f, -0.18f, -0.05f
};

// ── น้ำหนักชั้น Output  W2[16][3] ──────────────────────────────────────────
//               Normal    Mild     Severe
static const float W2[16][3] = {
  { 0.70f, -0.30f, -0.50f },   // N0:  low freq  → Normal
  { 0.68f, -0.28f, -0.48f },   // N1
  { 0.65f, -0.25f, -0.45f },   // N2
  { 0.62f, -0.22f, -0.42f },   // N3
  { 0.58f, -0.18f, -0.38f },   // N4
  { 0.54f, -0.14f, -0.34f },   // N5
  {-0.25f,  0.75f, -0.15f },   // N6:  mid freq  → Mild Stress
  {-0.22f,  0.72f, -0.12f },   // N7
  {-0.18f,  0.68f, -0.08f },   // N8
  {-0.14f,  0.63f, -0.04f },   // N9
  {-0.10f,  0.57f,  0.05f },   // N10
  {-0.40f, -0.20f,  0.80f },   // N11: high freq → Severe Stress
  {-0.36f, -0.16f,  0.76f },   // N12
  {-0.32f, -0.12f,  0.72f },   // N13
  {-0.27f, -0.07f,  0.67f },   // N14
  { 0.10f,  0.10f,  0.10f },   // N15: overall energy (shared)
};

// Bias ชั้น Output [3]
static const float B2[3] = { 0.10f, -0.05f, -0.08f };

// ── Activation functions ──────────────────────────────────────────────────
inline float relu(float x) { return x > 0.0f ? x : 0.0f; }

inline void softmax(float* x, int n) {
  float maxVal = x[0];
  for (int i = 1; i < n; i++) if (x[i] > maxVal) maxVal = x[i];
  float sum = 0.0f;
  for (int i = 0; i < n; i++) { x[i] = expf(x[i] - maxVal); sum += x[i]; }
  for (int i = 0; i < n; i++) x[i] /= (sum + 1e-9f);
}

// ── Forward Pass ──────────────────────────────────────────────────────────
// features[32] : FFT band energies, normalized 0.0–1.0
// probs[3]     : output probabilities [Normal, Mild, Severe]
// return       : class index with highest probability
inline int forward(const float features[32], float probs[3]) {
  float h[16] = {0.0f};

  // Layer 1: 32 → 16  (ReLU)
  for (int j = 0; j < HIDDEN_SIZE; j++) {
    float s = B1[j];
    for (int i = 0; i < NUM_FEATURES; i++) s += features[i] * W1[i][j];
    h[j] = relu(s);
  }

  // Layer 2: 16 → 3
  for (int k = 0; k < NUM_CLASSES; k++) {
    float s = B2[k];
    for (int j = 0; j < HIDDEN_SIZE; j++) s += h[j] * W2[j][k];
    probs[k] = s;
  }

  softmax(probs, NUM_CLASSES);

  int best = 0;
  for (int k = 1; k < NUM_CLASSES; k++) if (probs[k] > probs[best]) best = k;
  return best;
}

} // namespace PlantModel

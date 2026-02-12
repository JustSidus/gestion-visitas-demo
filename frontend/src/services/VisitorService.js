import api from "../api/api";

export default {

  /**
   * Crear un nuevo visitante
   */
  async create(visitor) {
    const response = await api.post('/visitors', visitor);
    return response.data;
  },

  /**
   * Buscar visitante por número de documento
   */
  async search(identity_document) {
    const response = await api.get(`/visitor/${identity_document}`);
    return response.data;
  },

  /**
   * Actualizar visitante existente
   * @param {number} id - ID del visitante
   * @param {object} visitor - Datos del visitante a actualizar
   */
  async update(id, visitor) {
    const response = await api.put(`/visitors/${id}`, visitor);
    return response.data;
  },
};
